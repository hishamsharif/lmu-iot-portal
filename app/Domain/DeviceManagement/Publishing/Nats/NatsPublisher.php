<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Nats;

interface NatsPublisher
{
    public function publish(string $subject, string $payload): void;
}
