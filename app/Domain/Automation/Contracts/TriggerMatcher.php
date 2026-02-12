<?php

declare(strict_types=1);

namespace App\Domain\Automation\Contracts;

use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Support\Collection;

interface TriggerMatcher
{
    /**
     * @return Collection<int, int>
     */
    public function matchTelemetryTriggers(DeviceTelemetryLog $telemetryLog): Collection;
}
