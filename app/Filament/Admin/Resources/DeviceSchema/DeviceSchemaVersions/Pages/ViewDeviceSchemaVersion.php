<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages;

use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceSchemaVersion extends ViewRecord
{
    protected static string $resource = DeviceSchemaVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
