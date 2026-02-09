<?php

declare(strict_types=1);

namespace App\Console\Commands\IoT;

use App\Domain\DeviceControl\Services\DeviceFeedbackReconciler;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Message\Payload;
use Illuminate\Console\Command;
use JsonException;

class ListenForDeviceStates extends Command
{
    protected $signature = 'iot:listen-for-device-states
                            {--host=127.0.0.1 : NATS broker host}
                            {--port=4223 : NATS broker port}';

    protected $description = 'Listen for device feedback messages from NATS and reconcile command/state lifecycle';

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
        /** @var DeviceFeedbackReconciler $reconciler */
        $reconciler = app(DeviceFeedbackReconciler::class);

        $natsSubject = '>';

        $this->info("Listening on: {$natsSubject}");
        $this->newLine();

        $client->subscribe($natsSubject, function (Payload $payload, ?string $replyTo) use ($host, $port, $reconciler): void {
            try {
                $subject = $payload->subject ?? '';
                $body = $payload->body;
                $mqttTopic = str_replace('.', '/', $subject);

                $decodedPayload = $this->decodePayload($body);

                if ($decodedPayload === null) {
                    return;
                }

                $result = $reconciler->reconcileInboundMessage(
                    mqttTopic: $mqttTopic,
                    payload: $decodedPayload,
                    host: $host,
                    port: $port,
                );

                if ($result === null) {
                    return;
                }

                $this->line(sprintf(
                    'Received %s [%s] for %s%s',
                    $result['topic'],
                    $result['purpose'],
                    $result['device_external_id'] ?? $result['device_uuid'],
                    $result['command_log_id'] !== null ? " (command #{$result['command_log_id']})" : '',
                ));
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

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $body): ?array
    {
        if (trim($body) === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
