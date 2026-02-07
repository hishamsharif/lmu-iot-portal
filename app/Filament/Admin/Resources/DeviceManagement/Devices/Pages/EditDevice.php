<?php

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
