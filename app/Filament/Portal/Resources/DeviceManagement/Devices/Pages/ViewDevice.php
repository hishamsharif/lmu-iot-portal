<?php

namespace App\Filament\Portal\Resources\DeviceManagement\Devices\Pages;

use App\Filament\Portal\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
