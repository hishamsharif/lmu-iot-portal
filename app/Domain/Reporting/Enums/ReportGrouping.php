<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportGrouping: string implements HasLabel
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Monthly = 'monthly';
    case ShiftSchedule = 'shift_schedule';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Monthly => 'Monthly',
            self::ShiftSchedule => 'Shift Schedule',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
