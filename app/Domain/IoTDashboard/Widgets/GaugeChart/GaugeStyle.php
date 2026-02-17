<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\GaugeChart;

use Filament\Support\Contracts\HasLabel;

enum GaugeStyle: string implements HasLabel
{
    case Classic = 'classic';
    case Progress = 'progress';
    case Minimal = 'minimal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Classic => 'Classic Gauge',
            self::Progress => 'Progress Gauge',
            self::Minimal => 'Minimal Gauge',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
