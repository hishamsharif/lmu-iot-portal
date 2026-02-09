<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DataIngestion\Contracts\HotStateStore;
use App\Domain\DataIngestion\Models\IngestionMessage;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;

class NatsKvHotStateStore implements HotStateStore
{
    public function __construct(
        private readonly NatsDeviceStateStore $stateStore,
    ) {}

    /**
     * @param  array<string, mixed>  $finalValues
     */
    public function store(Device $device, SchemaVersionTopic $topic, array $finalValues, IngestionMessage $ingestionMessage): void
    {
        $mqttTopic = $topic->resolvedTopic($device);
        $status = $ingestionMessage->status;
        $host = config('ingestion.nats.host', '127.0.0.1');
        $port = config('ingestion.nats.port', 4223);

        $this->stateStore->store(
            deviceUuid: $device->uuid,
            topic: $mqttTopic,
            payload: [
                'values' => $finalValues,
                'ingestion_message_id' => $ingestionMessage->id,
                'status' => $status,
                'recorded_at' => now()->toIso8601String(),
            ],
            host: is_string($host) && $host !== '' ? $host : '127.0.0.1',
            port: is_numeric($port) ? (int) $port : 4223,
        );
    }
}
