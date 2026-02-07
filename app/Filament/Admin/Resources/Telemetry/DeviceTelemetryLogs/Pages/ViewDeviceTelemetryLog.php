<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Telemetry\DeviceTelemetryLogs\Pages;

use App\Filament\Admin\Resources\Telemetry\DeviceTelemetryLogs\DeviceTelemetryLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceTelemetryLog extends ViewRecord
{
    protected static string $resource = DeviceTelemetryLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
