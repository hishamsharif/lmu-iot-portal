<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

interface NatsPublisherFactory
{
    public function make(string $host, int $port): NatsPublisher;
}
