<?php

declare(strict_types=1);

namespace App\Console\Commands\IoT;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\DeviceStateReceived;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Illuminate\Console\Command;

use function Laravel\Prompts\search;

class MockDeviceCommand extends Command
{
    protected $signature = 'iot:mock-device {device_uuid? : The UUID of the device to mock (optional)}
                            {--host= : NATS broker host}
                            {--port= : NATS broker port}
                            {--delay=1 : Seconds to wait before responding with state}';

    protected $description = 'Mock an IoT device: subscribe to command topics and respond with state on publish topics';

    public function handle(): int
    {
        $uuid = $this->argument('device_uuid');
        $host = $this->resolveHost();
        $port = $this->resolvePort();
        $delay = (int) $this->option('delay');

        // If no UUID provided, let user search and select a device
        if (! $uuid) {
            $device = $this->searchAndSelectDevice();
            if (! $device) {
                $this->error('No device selected.');

                return 1;
            }
        } else {
            /** @var Device|null $device */
            $device = Device::query()
                ->where('uuid', $uuid)
                ->with(['deviceType', 'schemaVersion.topics.parameters'])
                ->first();

            if (! $device) {
                $this->error("Device with UUID {$uuid} not found.");

                return 1;
            }
        }

        $subscribeTopics = $device->schemaVersion?->topics
            ?->filter(fn (SchemaVersionTopic $t): bool => $t->isSubscribe())
            ->sortBy('sequence');

        $publishTopics = $device->schemaVersion?->topics
            ?->filter(fn (SchemaVersionTopic $t): bool => $t->isPublish())
            ->sortBy('sequence');

        if (! $subscribeTopics || $subscribeTopics->isEmpty()) {
            $this->error('No subscribe (command) topics found for this device schema.');

            return 1;
        }

        $this->info("Mock device starting: {$device->name} ({$device->uuid})");
        $this->newLine();

        $configuration = new Configuration([
            'host' => $host,
            'port' => $port,
        ]);

        $client = new Client($configuration);

        $baseTopic = $device->deviceType?->protocol_config?->getBaseTopic() ?? 'device';
        $identifier = $device->external_id ?: $device->uuid;

        foreach ($subscribeTopics as $subscribeTopic) {
            $mqttTopic = trim($baseTopic, '/').'/'.$identifier.'/'.$subscribeTopic->suffix;
            $natsSubject = str_replace('/', '.', $mqttTopic);

            $this->line("  Subscribing to: <info>{$natsSubject}</info> (MQTT: {$mqttTopic})");

            $client->subscribe($natsSubject, function ($body) use ($device, $subscribeTopic, $publishTopics, $client, $baseTopic, $identifier, $delay, $host, $port): void {
                $payload = is_string($body) ? json_decode($body, true) : $body;

                $this->newLine();
                $this->warn('━━━ Command Received ━━━');
                $this->line('  Topic: <info>'.$subscribeTopic->suffix.'</info>');
                $this->line('  Payload: '.json_encode($payload, JSON_PRETTY_PRINT));

                if ($delay > 0) {
                    $this->line("  Applying command (waiting {$delay}s)...");
                    sleep($delay);
                }

                if ($publishTopics && $publishTopics->isNotEmpty()) {
                    $firstPublishTopic = $publishTopics->first();
                    $responseMqttTopic = trim($baseTopic, '/').'/'.$identifier.'/'.$firstPublishTopic->suffix;
                    $responseNatsSubject = str_replace('/', '.', $responseMqttTopic);

                    $statePayload = is_array($payload) ? $payload : [];

                    $encodedState = json_encode($statePayload);
                    $encodedState = is_string($encodedState) ? $encodedState : '{}';

                    $client->publish($responseNatsSubject, $encodedState);

                    $this->info('  State published → '.$responseNatsSubject);
                    $this->line('  State: '.$encodedState);

                    event(new DeviceStateReceived(
                        topic: $responseMqttTopic,
                        deviceUuid: $device->uuid,
                        deviceExternalId: $device->external_id,
                        payload: $statePayload,
                    ));

                    try {
                        /** @var NatsDeviceStateStore $stateStore */
                        $stateStore = app(NatsDeviceStateStore::class);
                        $stateStore->store($device->uuid, $responseMqttTopic, $statePayload, $host, $port);
                    } catch (\Throwable $e) {
                        $this->warn("  Could not persist state to KV: {$e->getMessage()}");
                    }
                } else {
                    $this->warn('  No publish topics configured — cannot send state response.');
                }
            });
        }

        $this->newLine();
        $this->info('Mock device is listening. Press Ctrl+C to stop.');
        $this->newLine();

        while (true) { /** @phpstan-ignore while.alwaysTrue */
            try {
                $client->process(1);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'No handler')) {
                    continue;
                }

                $this->error('NATS error: '.$e->getMessage());
                sleep(1);
            }
        }
    }

    private function resolveHost(): string
    {
        $hostOption = $this->option('host');

        if (is_string($hostOption) && trim($hostOption) !== '') {
            return trim($hostOption);
        }

        $host = config('iot.nats.host', '127.0.0.1');

        return is_string($host) && trim($host) !== '' ? trim($host) : '127.0.0.1';
    }

    private function resolvePort(): int
    {
        $portOption = $this->option('port');

        if (is_numeric($portOption)) {
            return (int) $portOption;
        }

        $port = config('iot.nats.port', 4223);

        return is_numeric($port) ? (int) $port : 4223;
    }

    /**
     * Search and select a device by name or external ID.
     */
    private function searchAndSelectDevice(): ?Device
    {
        /** @var string|int $deviceId */
        $deviceId = search(
            label: 'Search for a device (by name or ID)',
            options: function (string $value) {
                if (strlen($value) === 0) {
                    return Device::query()
                        ->limit(10)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Device $d): array => [
                            $d->id => "{$d->name} ({$d->external_id})",
                        ])
                        ->all();
                }

                return Device::query()
                    ->where('name', 'like', "%{$value}%")
                    ->orWhere('external_id', 'like', "%{$value}%")
                    ->limit(10)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Device $d): array => [
                        $d->id => "{$d->name} ({$d->external_id})",
                    ])
                    ->all();
            },
            placeholder: 'Type to search...',
        );

        return Device::query()
            ->where('id', $deviceId)
            ->with(['deviceType', 'schemaVersion.topics.parameters'])
            ->first();
    }
}
