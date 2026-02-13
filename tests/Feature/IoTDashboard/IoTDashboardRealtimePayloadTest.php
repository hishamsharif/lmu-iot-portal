<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Telemetry\Services\TelemetryLogRecorder;
use App\Events\TelemetryReceived;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('broadcasts realtime payload data required by line widgets', function (): void {
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create();

    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    foreach (['V1', 'V2', 'V3'] as $sequence => $key) {
        ParameterDefinition::factory()->create([
            'schema_version_topic_id' => $topic->id,
            'key' => $key,
            'label' => "Voltage {$key}",
            'json_path' => "voltages.{$key}",
            'type' => ParameterDataType::Decimal,
            'required' => true,
            'is_critical' => false,
            'mutation_expression' => null,
            'validation_error_code' => null,
            'sequence' => $sequence + 1,
            'is_active' => true,
        ]);
    }

    $device = Device::factory()->create([
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    $log = app(TelemetryLogRecorder::class)->record(
        device: $device,
        payload: [
            'voltages' => [
                'V1' => 229.6,
                'V2' => 230.2,
                'V3' => 231.4,
            ],
        ],
        topicSuffix: 'telemetry',
    );

    $event = new TelemetryReceived($log->fresh(['device']));
    $channels = $event->broadcastOn();
    $payload = $event->broadcastWith();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe('telemetry')
        ->and($event->broadcastAs())->toBe('telemetry.received')
        ->and($payload['device_uuid'] ?? null)->toBe($device->uuid)
        ->and($payload['schema_version_topic_id'] ?? null)->toBe($topic->id)
        ->and($payload['transformed_values']['V1'] ?? null)->toBe(229.6)
        ->and($payload['transformed_values']['V2'] ?? null)->toBe(230.2)
        ->and($payload['transformed_values']['V3'] ?? null)->toBe(231.4);
});
