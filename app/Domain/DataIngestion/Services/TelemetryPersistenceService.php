<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DataIngestion\Models\IngestionMessage;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Events\TelemetryReceived;
use Illuminate\Support\Carbon;

class TelemetryPersistenceService
{
    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, mixed>  $finalValues
     * @param  array<string, mixed>|null  $mutatedValues
     * @param  array<string, mixed>  $validationErrors
     */
    public function persist(
        Device $device,
        DeviceSchemaVersion $schemaVersion,
        ?SchemaVersionTopic $topic,
        array $rawPayload,
        array $finalValues,
        ValidationStatus $validationStatus,
        IngestionMessage $ingestionMessage,
        string $processingState,
        ?array $mutatedValues = null,
        array $validationErrors = [],
        ?Carbon $recordedAt = null,
        ?Carbon $receivedAt = null,
    ): DeviceTelemetryLog {
        $resolvedReceivedAt = $receivedAt ?? now();
        $resolvedRecordedAt = $recordedAt ?? $resolvedReceivedAt;

        $telemetryLog = DeviceTelemetryLog::create([
            'device_id' => $device->id,
            'device_schema_version_id' => $schemaVersion->id,
            'schema_version_topic_id' => $topic?->id,
            'ingestion_message_id' => $ingestionMessage->id,
            'raw_payload' => $rawPayload,
            'mutated_values' => $mutatedValues,
            'transformed_values' => $finalValues,
            'validation_errors' => $validationErrors,
            'validation_status' => $validationStatus,
            'processing_state' => $processingState,
            'recorded_at' => $resolvedRecordedAt,
            'received_at' => $resolvedReceivedAt,
        ]);

        $telemetryLog->loadMissing('device');
        event(new TelemetryReceived($telemetryLog));

        return $telemetryLog;
    }
}
