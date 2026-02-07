<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Enums;

use Filament\Support\Contracts\HasLabel;

enum TopicDirection: string implements HasLabel
{
    case Publish = 'publish';
    case Subscribe = 'subscribe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Publish => 'Publish (Device → Platform)',
            self::Subscribe => 'Subscribe (Platform → Device)',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
