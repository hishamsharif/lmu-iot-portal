<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Telemetry\Services\TelemetryLogRecorder;
use App\Events\TelemetryReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('broadcasts telemetry received events after recording', function (): void {
    Event::fake([TelemetryReceived::class]);

    $schemaVersion = DeviceSchemaVersion::factory()->create();
    SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'suffix' => 'telemetry',
    ]);

    $device = Device::factory()->create([
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    $recorder = new TelemetryLogRecorder;

    $recorder->record($device, ['temp' => 21.5], topicSuffix: 'telemetry');

    Event::assertDispatched(TelemetryReceived::class);
});
