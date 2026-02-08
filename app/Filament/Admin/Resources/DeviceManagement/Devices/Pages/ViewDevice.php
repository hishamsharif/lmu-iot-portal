<?php

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('controlDashboard')
                ->label('Control Dashboard')
                ->icon(Heroicon::OutlinedCommandLine)
                ->url(fn (): string => DeviceResource::getUrl('control-dashboard', ['record' => $this->record])),
            Actions\EditAction::make(),
        ];
    }
}
