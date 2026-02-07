<?php

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages;

use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceTypes extends ListRecords
{
    protected static string $resource = DeviceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
