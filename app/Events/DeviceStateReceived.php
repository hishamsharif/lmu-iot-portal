<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DeviceStateReceived implements ShouldBroadcastNow
{
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $topic,
        public string $deviceUuid,
        public ?string $deviceExternalId,
        public array $payload,
        public ?int $commandLogId = null,
        public ?Carbon $receivedAt = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("device-control.{$this->deviceUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'device.state.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'topic' => $this->topic,
            'device_uuid' => $this->deviceUuid,
            'device_external_id' => $this->deviceExternalId,
            'payload' => $this->payload,
            'command_log_id' => $this->commandLogId,
            'received_at' => ($this->receivedAt ?? Carbon::now())->toIso8601String(),
        ];
    }
}
