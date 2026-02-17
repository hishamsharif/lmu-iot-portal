<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\BarChart;

use Filament\Support\Contracts\HasLabel;

enum BarInterval: string implements HasLabel
{
    case Hourly = 'hourly';
    case Daily = 'daily';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
