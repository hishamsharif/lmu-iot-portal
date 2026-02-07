<?php

declare(strict_types=1);

namespace Database\Factories\Domain\DeviceManagement\Models;

use App\Domain\DeviceManagement\Enums\HttpAuthType;
use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\DeviceManagement\Models\DeviceType>
 */
class DeviceTypeFactory extends Factory
{
    protected $model = DeviceType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $protocol = $this->faker->randomElement([ProtocolType::Mqtt, ProtocolType::Http]);

        return [
            'organization_id' => null, // Default to global
            'key' => Str::slug($this->faker->unique()->words(2, true).'_'.Str::lower($this->faker->lexify('???')), '_'),
            'name' => $this->faker->words(3, true),
            'default_protocol' => $protocol,
            'protocol_config' => $protocol === ProtocolType::Mqtt
                ? $this->mqttConfig()
                : $this->httpConfig(),
        ];
    }

    /**
     * Indicate that this is a global catalog entry.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => null,
        ]);
    }

    /**
     * Indicate that this belongs to a specific organization.
     */
    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Indicate that this uses MQTT protocol.
     */
    public function mqtt(): static
    {
        return $this->state(fn (array $attributes) => [
            'default_protocol' => ProtocolType::Mqtt,
            'protocol_config' => $this->mqttConfig(),
        ]);
    }

    /**
     * Indicate that this uses HTTP protocol.
     */
    public function http(): static
    {
        return $this->state(fn (array $attributes) => [
            'default_protocol' => ProtocolType::Http,
            'protocol_config' => $this->httpConfig(),
        ]);
    }

    /**
     * Generate MQTT protocol configuration.
     *
     * @return array<string, mixed>
     */
    protected function mqttConfig(): array
    {
        return (new MqttProtocolConfig(
            brokerHost: $this->faker->domainName,
            brokerPort: $this->faker->randomElement([1883, 8883]),
            username: $this->faker->userName,
            password: $this->faker->password,
            useTls: $this->faker->boolean(30),
            baseTopic: 'device',
        ))->toArray();
    }

    /**
     * Generate HTTP protocol configuration.
     *
     * @return array<string, mixed>
     */
    protected function httpConfig(): array
    {
        $authType = $this->faker->randomElement([HttpAuthType::None, HttpAuthType::Bearer, HttpAuthType::Basic]);

        return (new HttpProtocolConfig(
            baseUrl: $this->faker->url,
            telemetryEndpoint: '/api/telemetry',
            method: $this->faker->randomElement(['POST', 'PUT']),
            headers: [
                'Content-Type' => 'application/json',
                'X-API-Version' => 'v1',
            ],
            authType: $authType,
            authToken: $authType === HttpAuthType::Bearer ? $this->faker->sha256 : null,
            authUsername: $authType === HttpAuthType::Basic ? $this->faker->userName : null,
            authPassword: $authType === HttpAuthType::Basic ? $this->faker->password : null,
            timeout: $this->faker->randomElement([15, 30, 60]),
        ))->toArray();
    }
}
