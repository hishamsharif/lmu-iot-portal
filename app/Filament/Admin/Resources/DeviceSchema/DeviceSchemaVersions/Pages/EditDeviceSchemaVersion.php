<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages;

use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceSchemaVersion extends EditRecord
{
    protected static string $resource = DeviceSchemaVersionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $template = $data['firmware_template'] ?? null;

        $data['firmware_template'] = is_string($template) ? $template : '';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
