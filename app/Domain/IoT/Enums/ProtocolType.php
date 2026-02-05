<?php

declare(strict_types=1);

namespace App\Domain\IoT\Enums;

enum ProtocolType: string
{
    case Mqtt = 'mqtt';
    case Http = 'http';

    public function label(): string
    {
        return match ($this) {
            self::Mqtt => 'MQTT',
            self::Http => 'HTTP',
        };
    }
}
