<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'firmware_filename' => 'esp32-rgb-light.ino',
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
