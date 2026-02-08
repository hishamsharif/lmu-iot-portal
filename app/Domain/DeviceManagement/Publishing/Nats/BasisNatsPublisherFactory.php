<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration;

final class BasisNatsPublisherFactory implements NatsPublisherFactory
{
    public function make(string $host, int $port): NatsPublisher
    {
        $configuration = new Configuration([
            'host' => $host,
            'port' => $port,
        ]);

        return new BasisNatsPublisher(new Client($configuration));
    }
}
