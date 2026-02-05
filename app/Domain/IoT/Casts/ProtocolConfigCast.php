<?php

declare(strict_types=1);

namespace App\Domain\IoT\Casts;

use App\Domain\IoT\Contracts\ProtocolConfigInterface;
use App\Domain\IoT\Enums\ProtocolType;
use App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig;
use App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<ProtocolConfigInterface, array<string, mixed>>
 */
class ProtocolConfigCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ProtocolConfigInterface
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);

        if (! is_array($data)) {
            return null;
        }

        $protocol = $attributes['default_protocol'] ?? null;

        if ($protocol === null) {
            throw new InvalidArgumentException('default_protocol attribute is required for ProtocolConfigCast');
        }

        $protocolType = $protocol instanceof ProtocolType ? $protocol : ProtocolType::from($protocol);

        return match ($protocolType) {
            ProtocolType::Mqtt => MqttProtocolConfig::fromArray($data),
            ProtocolType::Http => HttpProtocolConfig::fromArray($data),
        };
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null) {
            return json_encode([]);
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value instanceof ProtocolConfigInterface) {
            return json_encode($value->toArray());
        }

        throw new InvalidArgumentException('Value must be an instance of ProtocolConfigInterface or an array');
    }
}
