<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceTypes\Enums\HttpAuthType;
use App\Domain\DeviceTypes\Enums\ProtocolType;
use App\Domain\DeviceTypes\Models\DeviceType;
use App\Domain\DeviceTypes\ValueObjects\Protocol\HttpProtocolConfig;
use App\Domain\DeviceTypes\ValueObjects\Protocol\MqttProtocolConfig;
use Illuminate\Database\Seeder;

class DeviceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Global Catalog Entries
        $this->createGlobalTypes();

        // Organization-specific override example (if organizations exist)
        if (\App\Domain\Shared\Models\Organization::count() > 0) {
            $this->createOrgSpecificTypes();
        }
    }

    /**
     * Create global catalog device types.
     */
    protected function createGlobalTypes(): void
    {
        // 1. Three-Phase Energy Meter (MQTT)
        DeviceType::create([
            'organization_id' => null,
            'key' => 'energy_meter_3phase',
            'name' => '3-Phase Energy Meter',
            'default_protocol' => ProtocolType::Mqtt,
            'protocol_config' => (new MqttProtocolConfig(
                brokerHost: 'mqtt.iot-platform.local',
                brokerPort: 1883,
                username: 'energy_meter_user',
                password: 'secure_mqtt_password',
                useTls: false,
                telemetryTopicTemplate: 'energy/:device_uuid/metrics',
                controlTopicTemplate: 'energy/:device_uuid/commands',
                qos: 1,
                retain: false,
            ))->toArray(),
        ]);

        // 2. RGB LED Actuator (MQTT)
        DeviceType::create([
            'organization_id' => null,
            'key' => 'led_actuator_rgb',
            'name' => 'RGB LED Actuator',
            'default_protocol' => ProtocolType::Mqtt,
            'protocol_config' => (new MqttProtocolConfig(
                brokerHost: 'mqtt.iot-platform.local',
                brokerPort: 1883,
                username: 'led_user',
                password: 'secure_mqtt_password',
                useTls: false,
                telemetryTopicTemplate: 'led/:device_uuid/status',
                controlTopicTemplate: 'led/:device_uuid/control',
                qos: 1,
                retain: true,
            ))->toArray(),
        ]);

        // 3. Environmental Sensor (HTTP)
        DeviceType::create([
            'organization_id' => null,
            'key' => 'environmental_sensor',
            'name' => 'Environmental Sensor (Temp/Humidity)',
            'default_protocol' => ProtocolType::Http,
            'protocol_config' => (new HttpProtocolConfig(
                baseUrl: 'https://api.iot-platform.local',
                telemetryEndpoint: '/v1/sensors/telemetry',
                controlEndpoint: null,
                method: 'POST',
                headers: [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                authType: HttpAuthType::Bearer,
                authToken: 'sample_bearer_token_for_sensors',
                timeout: 30,
            ))->toArray(),
        ]);

        // 4. Industrial Temperature Sensor (MQTT with TLS)
        DeviceType::create([
            'organization_id' => null,
            'key' => 'industrial_temp_sensor',
            'name' => 'Industrial Temperature Sensor',
            'default_protocol' => ProtocolType::Mqtt,
            'protocol_config' => (new MqttProtocolConfig(
                brokerHost: 'secure-mqtt.iot-platform.local',
                brokerPort: 8883,
                username: 'temp_sensor_user',
                password: 'secure_tls_password',
                useTls: true,
                telemetryTopicTemplate: 'industrial/:device_uuid/temperature',
                controlTopicTemplate: 'industrial/:device_uuid/calibrate',
                qos: 2,
                retain: false,
            ))->toArray(),
        ]);

        // 5. Smart Thermostat (HTTP with Basic Auth)
        DeviceType::create([
            'organization_id' => null,
            'key' => 'smart_thermostat',
            'name' => 'Smart Thermostat',
            'default_protocol' => ProtocolType::Http,
            'protocol_config' => (new HttpProtocolConfig(
                baseUrl: 'https://thermostat-api.example.com',
                telemetryEndpoint: '/api/v2/devices/readings',
                controlEndpoint: '/api/v2/devices/setpoint',
                method: 'POST',
                headers: ['X-Device-Type' => 'thermostat'],
                authType: HttpAuthType::Basic,
                authUsername: 'thermostat_admin',
                authPassword: 'basic_auth_password',
                timeout: 15,
            ))->toArray(),
        ]);
    }

    /**
     * Create organization-specific device type overrides.
     */
    protected function createOrgSpecificTypes(): void
    {
        $firstOrg = \App\Domain\Shared\Models\Organization::first();

        if ($firstOrg) {
            // Organization-specific override of energy meter with custom MQTT broker
            DeviceType::create([
                'organization_id' => $firstOrg->id,
                'key' => 'energy_meter_3phase',
                'name' => '3-Phase Energy Meter (Custom Broker)',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'custom-broker.organization.local',
                    brokerPort: 1883,
                    username: 'custom_user',
                    password: 'custom_password',
                    useTls: false,
                    telemetryTopicTemplate: 'org/energy/:device_uuid/data',
                    controlTopicTemplate: 'org/energy/:device_uuid/ctrl',
                    qos: 1,
                    retain: false,
                ))->toArray(),
            ]);
        }
    }
}
