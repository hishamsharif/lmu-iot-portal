<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\TelemetryIncoming;

final readonly class DevicePublishingSimulator
{
    public function __construct(
        private NatsPublisherFactory $publisherFactory,
    ) {}

    /**
     * Simulate device -> platform publishing.
     *
     * @param  (callable(int $iteration, string $mqttTopic, array<string, mixed> $payload, SchemaVersionTopic $topic): void)|null  $onBeforePublish
     * @param  (callable(int $iteration, string $mqttTopic, \Throwable $exception, SchemaVersionTopic $topic): void)|null  $onPublishFailed
     */
    public function simulate(
        Device $device,
        int $count = 10,
        int $intervalSeconds = 1,
        ?int $schemaVersionTopicId = null,
        string $host = '127.0.0.1',
        int $port = 4223,
        ?callable $onBeforePublish = null,
        ?callable $onPublishFailed = null,
    ): void {
        $device->loadMissing('deviceType', 'schemaVersion.topics.parameters');

        $topics = $device->schemaVersion?->topics
            ?->filter(fn (SchemaVersionTopic $topic): bool => $topic->isPublish())
            ->when(
                $schemaVersionTopicId !== null,
                fn ($collection) => $collection->where('id', $schemaVersionTopicId),
            )
            ->sortBy('sequence');

        if (! $topics || $topics->isEmpty()) {
            return;
        }

        $publisher = $this->publisherFactory->make($host, $port);

        for ($i = 1; $i <= $count; $i++) {
            foreach ($topics as $topic) {
                $payload = $this->generateRandomPayload($topic);
                $mqttTopic = $this->resolveTopicWithExternalId($device, $topic);

                if ($onBeforePublish !== null) {
                    $onBeforePublish($i, $mqttTopic, $payload, $topic);
                }

                // NATS MQTT uses direct subject mapping; convert MQTT topic to NATS subject.
                $natsSubject = str_replace('/', '.', $mqttTopic);

                $encodedPayload = json_encode($payload);
                $encodedPayload = is_string($encodedPayload) ? $encodedPayload : '{}';

                try {
                    $publisher->publish($natsSubject, $encodedPayload);

                    event(new TelemetryIncoming(
                        topic: $mqttTopic,
                        deviceUuid: $device->uuid,
                        deviceExternalId: $device->external_id,
                        payload: $payload,
                    ));
                } catch (\Throwable $exception) {
                    report($exception);

                    if ($onPublishFailed !== null) {
                        $onPublishFailed($i, $mqttTopic, $exception, $topic);
                    }
                }
            }

            if ($i < $count && $intervalSeconds > 0) {
                sleep($intervalSeconds);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateRandomPayload(SchemaVersionTopic $topic): array
    {
        $topic->loadMissing('parameters');

        $payload = [];

        $topic->parameters
            ->where('is_active', true)
            ->sortBy('sequence')
            ->each(function ($parameter) use (&$payload): void {
                $value = $this->generateRandomValue($parameter->type);
                $payload = $parameter->placeValue($payload, $value);
            });

        return $payload;
    }

    private function generateRandomValue(ParameterDataType $type): mixed
    {
        return match ($type) {
            ParameterDataType::Integer => rand(0, 100),
            ParameterDataType::Decimal => rand(0, 1000) / 10,
            ParameterDataType::Boolean => (bool) rand(0, 1),
            ParameterDataType::String => 'Value_'.rand(100, 999),
            ParameterDataType::Json => ['v' => rand(1, 5)],
        };
    }

    private function resolveTopicWithExternalId(Device $device, SchemaVersionTopic $topic): string
    {
        $baseTopic = $device->deviceType?->protocol_config?->getBaseTopic() ?? 'device';
        $identifier = $device->external_id ?: $device->uuid;

        return trim($baseTopic, '/').'/'.$identifier.'/'.$topic->suffix;
    }
}
