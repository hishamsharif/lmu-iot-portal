<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Domain\Telemetry\Services\TelemetryLogRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('persists raw and transformed telemetry payloads', function (): void {
    $schemaVersion = DeviceSchemaVersion::factory()->create();

    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'temp_c',
        'json_path' => 'temp_c',
        'type' => ParameterDataType::Decimal,
        'required' => true,
        'is_critical' => false,
        'mutation_expression' => [
            '+' => [
                ['var' => 'val'],
                1,
            ],
        ],
        'sequence' => 1,
        'is_active' => true,
    ]);

    DerivedParameterDefinition::factory()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'temp_f',
        'data_type' => ParameterDataType::Decimal,
        'expression' => [
            '+' => [
                [
                    '*' => [
                        ['var' => 'temp_c'],
                        1.8,
                    ],
                ],
                32,
            ],
        ],
        'dependencies' => ['temp_c'],
    ]);

    $device = Device::factory()->create([
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    $payload = ['temp_c' => 10];
    $recorder = new TelemetryLogRecorder;

    $log = $recorder->record($device, $payload, topicSuffix: 'telemetry');

    expect($log->raw_payload)->toBe($payload)
        ->and($log->transformed_values)->toMatchArray([
            'temp_c' => 11.0,
            'temp_f' => 51.8,
        ])
        ->and($log->validation_status)->toBe(ValidationStatus::Valid)
        ->and($log->schema_version_topic_id)->toBe($topic->id);
});

it('marks telemetry as invalid when critical validation fails', function (): void {
    $schemaVersion = DeviceSchemaVersion::factory()->create();

    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'humidity',
        'json_path' => 'humidity',
        'type' => ParameterDataType::Decimal,
        'required' => true,
        'is_critical' => true,
        'mutation_expression' => null,
        'sequence' => 1,
        'is_active' => true,
    ]);

    $device = Device::factory()->create([
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    $recorder = new TelemetryLogRecorder;

    $log = $recorder->record($device, [], topicSuffix: 'telemetry');

    expect($log->validation_status)->toBe(ValidationStatus::Invalid);
});

it('can filter telemetry logs by recorded time range', function (): void {
    $device = Device::factory()->create();

    DeviceTelemetryLog::factory()->forDevice($device)->create([
        'recorded_at' => Carbon::parse('2026-02-06 09:00:00'),
    ]);

    DeviceTelemetryLog::factory()->forDevice($device)->create([
        'recorded_at' => Carbon::parse('2026-02-06 11:00:00'),
    ]);

    $logs = DeviceTelemetryLog::query()
        ->whereBetween('recorded_at', [
            Carbon::parse('2026-02-06 10:00:00'),
            Carbon::parse('2026-02-06 12:00:00'),
        ])
        ->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()?->recorded_at?->toDateTimeString())->toBe('2026-02-06 11:00:00');
});
