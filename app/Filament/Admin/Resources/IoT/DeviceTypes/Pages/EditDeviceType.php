<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Pages;

use App\Domain\DeviceTypes\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\DeviceTypes\ValueObjects\Protocol\MqttProtocolConfig;
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['protocol_config']) && $data['protocol_config'] instanceof HttpProtocolConfig) {
            $data['protocol_config'] = $data['protocol_config']->toArray();
            $data['protocol_config']['command_endpoint'] = $data['protocol_config']['control_endpoint'] ?? null;
        }

        if (isset($data['protocol_config']) && $data['protocol_config'] instanceof MqttProtocolConfig) {
            $data['protocol_config'] = $data['protocol_config']->toArray();
            $data['protocol_config']['command_topic_template'] = $data['protocol_config']['control_topic_template'] ?? null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CreateDeviceType::buildProtocolConfig($data);
    }
}
