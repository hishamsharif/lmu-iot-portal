<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportType: string implements HasLabel
{
    case ParameterValues = 'parameter_values';
    case CounterConsumption = 'counter_consumption';
    case StateUtilization = 'state_utilization';

    public function getLabel(): string
    {
        return match ($this) {
            self::ParameterValues => 'Raw Parameter Values',
            self::CounterConsumption => 'Counter Consumption',
            self::StateUtilization => 'State Utilization',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
