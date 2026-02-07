<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommandStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Completed = 'completed';
    case Failed = 'failed';
    case Timeout = 'timeout';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Acknowledged => 'Acknowledged',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Timeout => 'Timeout',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
