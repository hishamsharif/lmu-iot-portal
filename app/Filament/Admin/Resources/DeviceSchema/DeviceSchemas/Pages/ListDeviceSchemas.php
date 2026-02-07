<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages;

use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\DeviceSchemaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceSchemas extends ListRecords
{
    protected static string $resource = DeviceSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
