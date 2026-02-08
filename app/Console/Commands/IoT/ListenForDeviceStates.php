<?php

declare(strict_types=1);

namespace App\Console\Commands\IoT;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Events\DeviceStateReceived;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Message\Payload;
use Illuminate\Console\Command;

class ListenForDeviceStates extends Command
{
    protected $signature = 'iot:listen-for-device-states
                            {--host=127.0.0.1 : NATS broker host}
                            {--port=4223 : NATS broker port}';

    protected $description = 'Listen for device state messages from NATS and broadcast to dashboard';

    public function handle(): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');

        $this->info('Starting device state listener...');
        $this->info("Connecting to NATS at {$host}:{$port}");

        $configuration = new Configuration([
            'host' => $host,
            'port' => $port,
        ]);

        $client = new Client($configuration);

        // Subscribe to all device messages: devices.{baseTopic_part}.{external_id}.{topic_suffix}
        // We'll filter for .state messages in the callback
        $natsSubject = 'devices.>';

        $this->info("Listening on: {$natsSubject}");
        $this->newLine();

        // Subscribe to wildcard topic
        // Note: basis-company/nats passes (Payload $payload, ?string $replyTo) to callbacks.
        // The NATS subject is available on the Payload object via $payload->subject.
        $client->subscribe($natsSubject, function (Payload $payload, ?string $replyTo) use ($host, $port): void {
            try {
                $subject = $payload->subject ?? '';
                $body = $payload->body;

                // Only process messages ending in .state
                if (! str_ends_with($subject, '.state')) {
                    return;
                }

                $this->info("Received: {$subject}");

                // Parse the NATS subject to extract components
                // Pattern: devices.{baseTopic_part}.{external_id}.state
                // Example: devices.dimmable-light.dimmable-light-01.state
                $parts = explode('.', $subject);

                // We expect at least 4 parts: devices, baseTopic, external_id, state
                if (count($parts) < 4) {
                    return;
                }

                // The last part is 'state'
                array_pop($parts);

                // The second-to-last part is the external_id
                $externalId = array_pop($parts);

                // The remaining parts form the base topic (excluding 'devices')
                // e.g., for "devices.dimmable-light.dimmable-light-01.state", we have ['devices', 'dimmable-light']
                array_shift($parts); // Remove 'devices'
                $baseTopicSuffix = implode('.', $parts); // 'dimmable-light'
                $baseTopicMqtt = 'devices/'.str_replace('.', '/', $baseTopicSuffix);

                // Reconstruct the MQTT topic for the event
                $mqttTopic = "{$baseTopicMqtt}/{$externalId}/state";

                // Parse the JSON payload
                $decodedPayload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($decodedPayload)) {
                    $decodedPayload = [];
                }

                // Look up the device by external_id and find one whose deviceType has a matching baseTopic
                $device = Device::query()
                    ->where('external_id', $externalId)
                    ->with('deviceType')
                    ->get()
                    ->firstWhere(function (Device $d) use ($baseTopicMqtt): bool {
                        $deviceBaseTopic = $d->deviceType?->protocol_config?->getBaseTopic() ?? 'device';

                        return $deviceBaseTopic === $baseTopicMqtt;
                    });

                if (! $device) {
                    $this->warn("  Device not found: external_id={$externalId}, baseTopic={$baseTopicMqtt}");

                    return;
                }

                $this->info("  Device matched: {$device->name} (UUID: {$device->uuid})");

                event(new DeviceStateReceived(
                    topic: $mqttTopic,
                    deviceUuid: $device->uuid,
                    deviceExternalId: $device->external_id,
                    payload: $decodedPayload,
                ));

                $this->info('  Event broadcast OK');

                try {
                    /** @var NatsDeviceStateStore $stateStore */
                    $stateStore = app(NatsDeviceStateStore::class);
                    $stateStore->store($device->uuid, $mqttTopic, $decodedPayload, $host, $port);
                    $this->info('  KV stored OK');
                } catch (\Throwable $e) {
                    $this->warn("  KV store failed: {$e->getMessage()}");
                }
            } catch (\Throwable $e) {
                $this->error("  Processing error: {$e->getMessage()}");
            }
        });

        $this->info('Device state listener is running. Press Ctrl+C to stop.');
        $this->newLine();

        // Keep the client processing messages
        while (true) { /** @phpstan-ignore while.alwaysTrue */
            try {
                $client->process(1);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'No handler')) {
                    continue;
                }

                sleep(1);
            }
        }
    }
}
