<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Throwable;

final class BasisNatsDeviceStateStore implements NatsDeviceStateStore
{
    private const string BUCKET_NAME = 'device-states';

    public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void
    {
        try {
            $client = $this->createClient($host, $port);
            $bucket = $client->getApi()->getBucket(self::BUCKET_NAME);

            $document = $this->normalizeDocument($bucket->get($deviceUuid));
            $document['topics'][$topic] = [
                'topic' => $topic,
                'payload' => $payload,
                'stored_at' => now()->toIso8601String(),
            ];

            $data = json_encode($document);
            $bucket->put($deviceUuid, is_string($data) ? $data : '{}');
        } catch (Throwable $exception) {
            if ($this->isJetStreamUnavailable($exception)) {
                logger()->warning('NATS KV hot-state write skipped: JetStream/KV unavailable.', [
                    'bucket' => self::BUCKET_NAME,
                    'device_uuid' => $deviceUuid,
                    'topic' => $topic,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
    }

    public function getLastState(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): ?array
    {
        $states = $this->getAllStates($deviceUuid, $host, $port);

        return $states === [] ? null : $states[0];
    }

    public function getAllStates(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): array
    {
        try {
            $client = $this->createClient($host, $port);
            $bucket = $client->getApi()->getBucket(self::BUCKET_NAME);
            $value = $bucket->get($deviceUuid);
        } catch (Throwable $exception) {
            if ($this->isJetStreamUnavailable($exception)) {
                return [];
            }

            throw $exception;
        }

        $document = $this->normalizeDocument($value);
        $states = array_values($document['topics']);

        usort($states, function (array $left, array $right): int {
            $leftTime = strtotime($left['stored_at']) ?: 0;
            $rightTime = strtotime($right['stored_at']) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return $states;
    }

    public function getStateByTopic(string $deviceUuid, string $topic, string $host = '127.0.0.1', int $port = 4223): ?array
    {
        try {
            $client = $this->createClient($host, $port);
            $bucket = $client->getApi()->getBucket(self::BUCKET_NAME);
            $value = $bucket->get($deviceUuid);
        } catch (Throwable $exception) {
            if ($this->isJetStreamUnavailable($exception)) {
                return null;
            }

            throw $exception;
        }

        $document = $this->normalizeDocument($value);

        return $document['topics'][$topic] ?? null;
    }

    private function createClient(string $host, int $port): Client
    {
        return new Client(new Configuration([
            'host' => $host,
            'port' => $port,
        ]));
    }

    private function isJetStreamUnavailable(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'No handler for message _REQS.')
            || str_contains($message, 'JS not enabled');
    }

    /**
     * @return array{topics: array<string, array{topic: string, payload: array<string, mixed>, stored_at: string}>}
     */
    private function normalizeDocument(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return ['topics' => []];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return ['topics' => []];
        }

        $normalizedTopics = [];
        $decodedTopics = $decoded['topics'] ?? null;

        if (is_array($decodedTopics)) {
            foreach ($decodedTopics as $topic => $state) {
                $fallbackTopic = is_string($topic) ? $topic : null;
                $normalizedState = $this->normalizeTopicState($state, $fallbackTopic);

                if ($normalizedState === null) {
                    continue;
                }

                $normalizedTopics[$normalizedState['topic']] = $normalizedState;
            }

            return ['topics' => $normalizedTopics];
        }

        $legacyState = $this->normalizeTopicState($decoded);

        if ($legacyState !== null) {
            $normalizedTopics[$legacyState['topic']] = $legacyState;
        }

        return ['topics' => $normalizedTopics];
    }

    /**
     * @return array{topic: string, payload: array<string, mixed>, stored_at: string}|null
     */
    private function normalizeTopicState(mixed $state, ?string $fallbackTopic = null): ?array
    {
        if (! is_array($state)) {
            return null;
        }

        $topic = $state['topic'] ?? $fallbackTopic;

        if (! is_string($topic) || trim($topic) === '') {
            return null;
        }

        $payload = $state['payload'] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        $storedAt = $state['stored_at'] ?? null;

        if (! is_string($storedAt) || trim($storedAt) === '') {
            return null;
        }

        /** @var array<string, mixed> $payload */
        return [
            'topic' => $topic,
            'payload' => $payload,
            'stored_at' => $storedAt,
        ];
    }
}
