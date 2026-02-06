<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\IoT\Enums\HttpAuthType;
use App\Domain\IoT\Enums\ProtocolType;
use App\Domain\IoT\Models\DeviceType;
use App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig;
use App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\IoT\Models\DeviceType>
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
            telemetryTopicTemplate: 'device/:device_uuid/data',
            controlTopicTemplate: 'device/:device_uuid/ctrl',
            qos: $this->faker->randomElement([0, 1, 2]),
            retain: $this->faker->boolean(20),
        ))->toArray();
    }

    /**
     * Generate HTTP protocol configuration.
     *
     * @return array<string, mixed>
     */
    protected function httpConfig(): array
    {
        return (new HttpProtocolConfig(
            baseUrl: $this->faker->url,
            telemetryEndpoint: '/api/telemetry',
            controlEndpoint: '/api/command',
            method: $this->faker->randomElement(['POST', 'PUT']),
            headers: [
                'Content-Type' => 'application/json',
                'X-API-Version' => 'v1',
            ],
            authType: $this->faker->randomElement([HttpAuthType::None, HttpAuthType::Bearer, HttpAuthType::Basic]),
            authToken: $this->faker->sha256,
            timeout: $this->faker->randomElement([15, 30, 60]),
        ))->toArray();
    }
}
