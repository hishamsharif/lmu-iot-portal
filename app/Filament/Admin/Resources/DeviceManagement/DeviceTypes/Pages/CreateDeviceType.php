<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages;

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeviceType extends CreateRecord
{
    protected static string $resource = DeviceTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return self::buildProtocolConfig($data);
    }

    /**
     * Build a typed protocol config value object from form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function buildProtocolConfig(array $data): array
    {
        if (! isset($data['default_protocol']) || ! isset($data['protocol_config'])) {
            return $data;
        }

        $protocolValue = $data['default_protocol'];
        if (! $protocolValue instanceof ProtocolType && ! is_string($protocolValue) && ! is_int($protocolValue)) {
            return $data;
        }

        $protocol = $protocolValue instanceof ProtocolType
            ? $protocolValue
            : ProtocolType::from($protocolValue);

        if (! is_array($data['protocol_config'])) {
            return $data;
        }

        /** @var array<string, mixed> $protocolConfig */
        $protocolConfig = $data['protocol_config'];

        $data['protocol_config'] = match ($protocol) {
            ProtocolType::Mqtt => MqttProtocolConfig::fromArray($protocolConfig),
            ProtocolType::Http => HttpProtocolConfig::fromArray($protocolConfig),
        };

        return $data;
    }
}
