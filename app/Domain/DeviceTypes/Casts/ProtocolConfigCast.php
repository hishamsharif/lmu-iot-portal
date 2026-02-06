<?php

declare(strict_types=1);

namespace App\Domain\DeviceTypes\Casts;

use App\Domain\DeviceTypes\Enums\ProtocolType;
use App\Domain\DeviceTypes\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\DeviceTypes\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\DeviceTypes\ValueObjects\Protocol\ProtocolConfigInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<ProtocolConfigInterface|null, mixed>
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

        if (! is_string($value)) {
            return null;
        }

        $data = json_decode($value, true);

        if (! is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $protocol = $attributes['default_protocol'] ?? null;

        if ($protocol === null) {
            throw new InvalidArgumentException('default_protocol attribute is required for ProtocolConfigCast');
        }

        if (! $protocol instanceof ProtocolType && ! is_string($protocol) && ! is_int($protocol)) {
            throw new InvalidArgumentException('default_protocol attribute must be a ProtocolType or scalar value');
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
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if ($value instanceof ProtocolConfigInterface) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException('Value must be an instance of ProtocolConfigInterface or an array');
    }
}
