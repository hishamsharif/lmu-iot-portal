<?php

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Pages;

use App\Filament\Admin\Resources\IoT\DeviceTypes\DeviceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceType extends EditRecord
{
    protected static string $resource = DeviceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
