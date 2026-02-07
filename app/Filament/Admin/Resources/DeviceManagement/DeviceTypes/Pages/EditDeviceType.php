<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages;

use App\Domain\DeviceManagement\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['protocol_config']) && ($data['protocol_config'] instanceof HttpProtocolConfig || $data['protocol_config'] instanceof MqttProtocolConfig)) {
            $data['protocol_config'] = $data['protocol_config']->toArray();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CreateDeviceType::buildProtocolConfig($data);
    }
}
