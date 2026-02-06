<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Pages;

use App\Domain\IoT\DeviceTypes\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\IoT\DeviceTypes\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\IoT\Enums\ProtocolType;
use App\Filament\Admin\Resources\IoT\DeviceTypes\DeviceTypeResource;
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

        /** @var array<string, mixed> $config */
        $config = $data['protocol_config'];

        // Map form field names to domain field names
        $config['control_topic_template'] = $config['command_topic_template'] ?? null;
        $config['control_endpoint'] = $config['command_endpoint'] ?? null;
        unset($config['command_topic_template'], $config['command_endpoint']);

        $data['protocol_config'] = match ($protocol) {
            ProtocolType::Mqtt => MqttProtocolConfig::fromArray($config),
            ProtocolType::Http => HttpProtocolConfig::fromArray($config),
        };

        return $data;
    }
}
