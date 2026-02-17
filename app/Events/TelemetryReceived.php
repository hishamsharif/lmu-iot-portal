<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $this->telemetryLog->loadMissing('device:id,uuid,external_id,organization_id');
        $organizationId = $this->telemetryLog->device?->organization_id;

        if (! is_numeric($organizationId)) {
            return [];
        }

        return [
            new PrivateChannel('iot-dashboard.organization.'.(int) $organizationId),
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
        $recordedAtValue = $recordedAt instanceof Carbon ? $recordedAt->toIso8601String() : null;

        return [
            'id' => $this->telemetryLog->id,
            'organization_id' => is_numeric($device?->organization_id) ? (int) $device->organization_id : null,
            'device_uuid' => $device?->uuid,
            'schema_version_topic_id' => $this->telemetryLog->schema_version_topic_id,
            'transformed_values' => $this->telemetryLog->transformed_values,
            'recorded_at' => $recordedAtValue,
        ];
    }
}
