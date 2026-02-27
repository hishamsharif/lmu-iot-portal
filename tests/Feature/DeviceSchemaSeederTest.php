<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use Database\Seeders\DeviceSchemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds rgb led controller firmware template from the esp-32-rgb-light file', function (): void {
    $this->seed(DeviceSchemaSeeder::class);

    $version = DeviceSchemaVersion::query()
        ->whereHas('schema.deviceType', fn ($query) => $query->where('key', 'rgb_led_controller'))
        ->where('status', 'active')
        ->orderBy('id')
        ->first();

    expect($version)->not->toBeNull()
        ->and($version?->firmware_template)->toBeString()
        ->and($version?->firmware_template)->toContain('PIN_WIFI_STATUS_LED')
        ->and($version?->firmware_template)->toContain('PIN_MQTT_STATUS_LED')
        ->and($version?->firmware_template)->toContain('const char* MQTT_FALLBACK_HOST = "{{MQTT_FALLBACK_HOST}}";')
        ->and($version?->firmware_template)->toContain('const char* MQTT_CLIENT   = "{{MQTT_CLIENT_ID}}"');
});

it('seeds mqtt broker host and port from iot config', function (): void {
    config([
        'iot.mqtt.host' => '10.0.0.42',
        'iot.mqtt.port' => 1883,
    ]);

    $this->seed(DeviceSchemaSeeder::class);

    $deviceType = DeviceType::query()
        ->where('key', 'rgb_led_controller')
        ->first();

    expect($deviceType)->not->toBeNull()
        ->and($deviceType?->protocol_config?->brokerHost)->toBe('10.0.0.42')
        ->and($deviceType?->protocol_config?->brokerPort)->toBe(1883);
});

it('seeds the energy meter telemetry contract with voltages, currents, counter energy, and enum state', function (): void {
    $this->seed(DeviceSchemaSeeder::class);

    $version = DeviceSchemaVersion::query()
        ->whereHas('schema.deviceType', fn ($query) => $query->where('key', 'energy_meter'))
        ->where('status', 'active')
        ->orderBy('id')
        ->first();

    expect($version)->not->toBeNull();

    $parameters = ParameterDefinition::query()
        ->whereHas('topic', fn ($query) => $query->where('device_schema_version_id', $version?->id)->where('key', 'telemetry'))
        ->orderBy('sequence')
        ->get()
        ->keyBy('key');

    expect($parameters->keys()->all())->toBe([
        'V1',
        'V2',
        'V3',
        'A1',
        'A2',
        'A3',
        'total_energy_kwh',
        'meter_state',
    ])
        ->and(data_get($parameters->get('A1'), 'unit'))->toBe('A')
        ->and(data_get($parameters->get('total_energy_kwh'), 'unit'))->toBe('kWh')
        ->and(data_get($parameters->get('total_energy_kwh'), 'validation_rules.category'))->toBe('counter')
        ->and(data_get($parameters->get('meter_state'), 'validation_rules.category'))->toBe('enum')
        ->and(data_get($parameters->get('meter_state'), 'validation_rules.enum'))->toBe(['idle', 'normal', 'fault']);
});

it('seeds the single-phase energy meter contract and firmware template', function (): void {
    $this->seed(DeviceSchemaSeeder::class);

    $version = DeviceSchemaVersion::query()
        ->whereHas('schema.deviceType', fn ($query) => $query->where('key', 'single_phase_energy_meter'))
        ->where('status', 'active')
        ->orderBy('id')
        ->first();

    expect($version)->not->toBeNull()
        ->and($version?->firmware_template)->toBeString()
        ->and($version?->firmware_template)->toContain('ESP32 Single-Phase Energy Meter (PZEM-014/016)')
        ->and($version?->firmware_template)->toContain('const char* MQTT_HOST = "{{MQTT_HOST}}";')
        ->and($version?->firmware_template)->toContain('const char* TOPIC_TELEMETRY = "{{TELEMETRY_TOPIC}}";');

    $parameters = ParameterDefinition::query()
        ->whereHas('topic', fn ($query) => $query->where('device_schema_version_id', $version?->id)->where('key', 'telemetry'))
        ->orderBy('sequence')
        ->get()
        ->keyBy('key');

    expect($parameters->keys()->all())->toBe([
        'voltage_v',
        'current_a',
        'active_power_w',
        'frequency_hz',
        'power_factor',
        'total_energy_kwh',
        'alarm_status',
        'read_ok',
        'modbus_error',
        'poll_ms',
    ])
        ->and(data_get($parameters->get('voltage_v'), 'unit'))->toBe('Volts')
        ->and(data_get($parameters->get('current_a'), 'unit'))->toBe('A')
        ->and(data_get($parameters->get('total_energy_kwh'), 'unit'))->toBe('kWh')
        ->and(data_get($parameters->get('total_energy_kwh'), 'validation_rules.category'))->toBe('counter');
});
