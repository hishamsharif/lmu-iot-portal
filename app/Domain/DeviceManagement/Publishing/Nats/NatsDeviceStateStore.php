<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

interface NatsDeviceStateStore
{
    /**
     * Store the latest device state in the NATS Key-Value bucket.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void;

    /**
     * Retrieve the last known device state from the NATS Key-Value bucket.
     *
     * @return array{topic: string, payload: array<string, mixed>, stored_at: string}|null
     */
    public function getLastState(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): ?array;
}
