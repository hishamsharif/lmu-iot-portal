<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoTDashboards\Pages;

use App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIoTDashboard extends EditRecord
{
    protected static string $resource = IoTDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('openDashboard')
                ->label('Open Dashboard')
                ->url(
                    fn (): string => route(
                        'filament.admin.pages.io-t-dashboard',
                        ['dashboard' => $this->getRecord()->getKey()],
                    ),
                    shouldOpenInNewTab: true,
                ),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
