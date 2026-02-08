<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

use Basis\Nats\Client;

final readonly class BasisNatsPublisher implements NatsPublisher
{
    public function __construct(
        private Client $client,
    ) {}

    public function publish(string $subject, string $payload): void
    {
        $this->client->publish($subject, $payload);
    }
}
