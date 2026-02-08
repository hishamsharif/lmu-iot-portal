<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Services;

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\CommandDispatched;
use App\Events\CommandSent;

final readonly class DeviceCommandDispatcher
{
    public function __construct(
        private NatsPublisherFactory $publisherFactory,
    ) {}

    /**
     * Dispatch a command from the platform to a device via NATS.
     *
     * Creates a DeviceCommandLog, publishes to the broker, and broadcasts
     * Reverb events at each lifecycle step.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        Device $device,
        SchemaVersionTopic $topic,
        array $payload,
        ?int $userId = null,
        string $host = '127.0.0.1',
        int $port = 4223,
    ): DeviceCommandLog {
        $device->loadMissing('deviceType');

        $commandLog = DeviceCommandLog::create([
            'device_id' => $device->id,
            'schema_version_topic_id' => $topic->id,
            'user_id' => $userId,
            'command_payload' => $payload,
            'status' => CommandStatus::Pending,
        ]);

        $commandLog->load('device', 'topic');

        event(new CommandDispatched($commandLog));

        $mqttTopic = $this->resolveTopicWithExternalId($device, $topic);
        $natsSubject = str_replace('/', '.', $mqttTopic);

        $encodedPayload = json_encode($payload);
        $encodedPayload = is_string($encodedPayload) ? $encodedPayload : '{}';

        try {
            $publisher = $this->publisherFactory->make($host, $port);
            $publisher->publish($natsSubject, $encodedPayload);

            $commandLog->update([
                'status' => CommandStatus::Sent,
                'sent_at' => now(),
            ]);

            $commandLog->refresh();

            event(new CommandSent($commandLog, $natsSubject));
        } catch (\Throwable $exception) {
            $commandLog->update([
                'status' => CommandStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            report($exception);
        }

        return $commandLog;
    }

    private function resolveTopicWithExternalId(Device $device, SchemaVersionTopic $topic): string
    {
        $baseTopic = $device->deviceType?->protocol_config?->getBaseTopic() ?? 'device';
        $identifier = $device->external_id ?: $device->uuid;

        return trim($baseTopic, '/').'/'.$identifier.'/'.$topic->suffix;
    }
}
