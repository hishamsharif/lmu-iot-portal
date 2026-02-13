<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoTDashboards\Pages;

use App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIoTDashboards extends ListRecords
{
    protected static string $resource = IoTDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
