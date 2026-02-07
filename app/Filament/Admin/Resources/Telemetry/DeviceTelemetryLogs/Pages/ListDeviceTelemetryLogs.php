<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Telemetry\DeviceTelemetryLogs\Pages;

use App\Filament\Admin\Resources\Telemetry\DeviceTelemetryLogs\DeviceTelemetryLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceTelemetryLogs extends ListRecords
{
    protected static string $resource = DeviceTelemetryLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
