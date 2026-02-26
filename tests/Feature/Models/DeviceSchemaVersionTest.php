<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceCertificate;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders firmware template with device specific placeholders', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create([
        'protocol_config' => [
            'broker_host' => 'localhost',
            'broker_port' => 1883,
            'username' => null,
            'password' => null,
            'use_tls' => false,
            'base_topic' => 'devices/rgb-led',
        ],
    ]);

    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();

    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'firmware_template' => <<<'CPP'
const char* DEVICE_ID = "{{DEVICE_ID}}";
const char* CONTROL_TOPIC = "{{CONTROL_TOPIC}}";
const char* STATE_TOPIC = "{{STATE_TOPIC}}";
CPP,
    ]);

    SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $version->id,
        'suffix' => 'control',
    ]);

    SchemaVersionTopic::factory()->stateTopic()->create([
        'device_schema_version_id' => $version->id,
        'suffix' => 'state',
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $version->id,
        'external_id' => 'rgb-led-01',
    ]);

    $renderedFirmware = $version->renderFirmwareForDevice($device);

    expect($renderedFirmware)
        ->toContain('const char* DEVICE_ID = "rgb-led-01";')
        ->toContain('const char* CONTROL_TOPIC = "devices/rgb-led/rgb-led-01/control";')
        ->toContain('const char* STATE_TOPIC = "devices/rgb-led/rgb-led-01/state";')
        ->not->toContain('{{DEVICE_ID}}');
});

it('falls back to uuid when device external id is not set', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();

    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'firmware_template' => 'const char* DEVICE_ID = "{{DEVICE_ID}}";',
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $version->id,
        'external_id' => null,
    ]);

    $renderedFirmware = $version->renderFirmwareForDevice($device);

    expect($renderedFirmware)->toContain("const char* DEVICE_ID = \"{$device->uuid}\";");
});

it('renders mqtt connectivity and x509 placeholders from active certificate state', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create([
        'protocol_config' => [
            'broker_host' => 'mqtt.local.test',
            'broker_port' => 8883,
            'username' => 'legacy-user',
            'password' => 'legacy-pass',
            'use_tls' => true,
            'base_topic' => 'devices/secure',
            'security_mode' => 'x509_mtls',
        ],
    ]);

    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();

    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'firmware_template' => <<<'CPP'
const char* MQTT_HOST = "{{MQTT_HOST}}";
const char* MQTT_FALLBACK_HOST = "{{MQTT_FALLBACK_HOST}}";
const uint16_t MQTT_PORT = {{MQTT_PORT}};
const bool MQTT_USE_TLS = {{MQTT_USE_TLS}};
const char* MQTT_SECURITY_MODE = "{{MQTT_SECURITY_MODE}}";
const char* TOPIC_PRESENCE = "{{PRESENCE_TOPIC}}";
const char* CA_CERT = R"PEM({{MQTT_TLS_CA_CERT_PEM}})PEM";
const char* CLIENT_CERT = R"PEM({{MQTT_TLS_CLIENT_CERT_PEM}})PEM";
const char* CLIENT_KEY = R"PEM({{MQTT_TLS_CLIENT_KEY_PEM}})PEM";
CPP,
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $version->id,
        'external_id' => 'secure-rgb-01',
    ]);

    DeviceCertificate::factory()->create([
        'device_id' => $device->id,
        'certificate_pem' => "-----BEGIN CERTIFICATE-----\nDEVICE_CERT\n-----END CERTIFICATE-----",
        'private_key_encrypted' => Crypt::encryptString("-----BEGIN PRIVATE KEY-----\nDEVICE_KEY\n-----END PRIVATE KEY-----"),
        'not_after' => now()->addDays(30),
        'revoked_at' => null,
        'revocation_reason' => null,
        'issued_at' => now(),
    ]);

    $caCertificatePath = storage_path('framework/testing/'.Str::uuid()->toString().'-ca.crt');
    file_put_contents($caCertificatePath, "-----BEGIN CERTIFICATE-----\nCA_CERT\n-----END CERTIFICATE-----");

    config([
        'iot.mqtt.host' => 'broker-fallback.local',
        'iot.pki.ca_certificate_path' => $caCertificatePath,
        'iot.presence.subject_prefix' => 'devices',
        'iot.presence.subject_suffix' => 'presence',
    ]);

    $renderedFirmware = $version->renderFirmwareForDevice($device);

    expect($renderedFirmware)
        ->toContain('const char* MQTT_HOST = "mqtt.local.test";')
        ->toContain('const char* MQTT_FALLBACK_HOST = "broker-fallback.local";')
        ->toContain('const uint16_t MQTT_PORT = 8883;')
        ->toContain('const bool MQTT_USE_TLS = true;')
        ->toContain('const char* MQTT_SECURITY_MODE = "x509_mtls";')
        ->toContain('const char* TOPIC_PRESENCE = "devices/secure-rgb-01/presence";')
        ->toContain('CA_CERT')
        ->toContain('DEVICE_CERT')
        ->toContain('DEVICE_KEY');
});

it('renders a device-reachable fallback broker host when configured mqtt host is an internal alias', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create([
        'protocol_config' => [
            'broker_host' => 'mqtt.iot-platform.local',
            'broker_port' => 1883,
            'username' => 'rgb_led',
            'password' => 'rgb_password',
            'use_tls' => false,
            'base_topic' => 'devices/rgb-led',
            'security_mode' => 'username_password',
        ],
    ]);

    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();

    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'firmware_template' => <<<'CPP'
const char* MQTT_HOST = "{{MQTT_HOST}}";
const char* MQTT_FALLBACK_HOST = "{{MQTT_FALLBACK_HOST}}";
const uint16_t MQTT_PORT = {{MQTT_PORT}};
CPP,
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $version->id,
        'external_id' => 'rgb-led-01',
    ]);

    config([
        'iot.mqtt.host' => 'nats',
        'iot.nats.host' => '10.0.0.42',
    ]);

    $renderedFirmware = $version->renderFirmwareForDevice($device);

    expect($renderedFirmware)
        ->toContain('const char* MQTT_HOST = "mqtt.iot-platform.local";')
        ->toContain('const char* MQTT_FALLBACK_HOST = "10.0.0.42";')
        ->toContain('const uint16_t MQTT_PORT = 1883;');
});
