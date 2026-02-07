<?php

declare(strict_types=1);

namespace App\Domain\Telemetry\Enums;

enum ValidationStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::Warning => 'Warning',
        };
    }
}
