<?php

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages;

use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceType extends ViewRecord
{
    protected static string $resource = DeviceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
