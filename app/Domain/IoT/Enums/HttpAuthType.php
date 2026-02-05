<?php

declare(strict_types=1);

namespace App\Domain\IoT\Enums;

enum HttpAuthType: string
{
    case None = 'none';
    case Basic = 'basic';
    case Bearer = 'bearer';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Authentication',
            self::Basic => 'Basic Auth',
            self::Bearer => 'Bearer Token',
        };
    }
}
