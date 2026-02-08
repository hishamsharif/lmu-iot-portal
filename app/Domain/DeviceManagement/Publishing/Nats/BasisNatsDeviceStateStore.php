<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration;

final class BasisNatsDeviceStateStore implements NatsDeviceStateStore
{
    private const string BUCKET_NAME = 'device-states';

    public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void
    {
        $client = $this->createClient($host, $port);
        $bucket = $client->getApi()->getBucket(self::BUCKET_NAME);

        $data = json_encode([
            'topic' => $topic,
            'payload' => $payload,
            'stored_at' => now()->toIso8601String(),
        ]);

        $bucket->put($deviceUuid, is_string($data) ? $data : '{}');
    }

    public function getLastState(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): ?array
    {
        $client = $this->createClient($host, $port);
        $bucket = $client->getApi()->getBucket(self::BUCKET_NAME);
        $value = $bucket->get($deviceUuid);

        if (! is_string($value)) {
            return null;
        }

        /** @var array{topic: string, payload: array<string, mixed>, stored_at: string}|null $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function createClient(string $host, int $port): Client
    {
        return new Client(new Configuration([
            'host' => $host,
            'port' => $port,
        ]));
    }
}
