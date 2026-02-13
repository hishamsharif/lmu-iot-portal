<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoTDashboards\Pages;

use App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIoTDashboard extends CreateRecord
{
    protected static string $resource = IoTDashboardResource::class;
}
