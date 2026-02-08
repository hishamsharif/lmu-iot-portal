<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TelemetryReceived implements ShouldBroadcastNow
{
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public DeviceTelemetryLog $telemetryLog,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('telemetry'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'telemetry.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $device = $this->telemetryLog->device;
        $recordedAt = $this->telemetryLog->getAttribute('recorded_at');
        $receivedAt = $this->telemetryLog->getAttribute('received_at');
        $validationStatus = $this->telemetryLog->getAttribute('validation_status');

        $recordedAtValue = $recordedAt instanceof Carbon ? $recordedAt->toIso8601String() : null;
        $receivedAtValue = $receivedAt instanceof Carbon ? $receivedAt->toIso8601String() : null;
        $validationStatusValue = is_string($validationStatus) ? $validationStatus : null;

        return [
            'id' => $this->telemetryLog->id,
            'device_uuid' => $device?->uuid,
            'device_external_id' => $device?->external_id,
            'schema_version_topic_id' => $this->telemetryLog->schema_version_topic_id,
            'raw_payload' => $this->telemetryLog->raw_payload,
            'transformed_values' => $this->telemetryLog->transformed_values,
            'validation_status' => $validationStatusValue,
            'recorded_at' => $recordedAtValue,
            'received_at' => $receivedAtValue,
        ];
    }
}
