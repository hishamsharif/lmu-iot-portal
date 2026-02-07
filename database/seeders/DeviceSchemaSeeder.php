<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Database\Seeder;

class DeviceSchemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thermalDeviceType = DeviceType::firstOrCreate(
            ['key' => 'thermal_sensor'],
            [
                'organization_id' => null,
                'name' => 'Thermal Sensor',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'thermal_sensor',
                    password: 'thermal_password',
                    useTls: false,
                    baseTopic: 'thermal',
                ))->toArray(),
            ]
        );

        $networkDeviceType = DeviceType::firstOrCreate(
            ['key' => 'network_gateway'],
            [
                'organization_id' => null,
                'name' => 'Network Gateway',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'network_gateway',
                    password: 'network_password',
                    useTls: false,
                    baseTopic: 'network',
                ))->toArray(),
            ]
        );

        $energyMeterType = DeviceType::firstOrCreate(
            ['key' => 'energy_meter'],
            [
                'organization_id' => null,
                'name' => 'Energy Meter',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'energy_meter',
                    password: 'energy_password',
                    useTls: false,
                    baseTopic: 'energy',
                ))->toArray(),
            ]
        );

        $thermalSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $thermalDeviceType->id,
            'name' => 'Thermal Sensor Contract',
        ]);

        $thermalVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $thermalSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Initial thermal sensor contract',
        ]);

        $thermalTelemetryTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $thermalVersion->id,
            'key' => 'telemetry',
        ], [
            'label' => 'Telemetry',
            'direction' => TopicDirection::Publish,
            'suffix' => 'telemetry',
            'description' => 'Temperature and humidity readings',
            'qos' => 1,
            'retain' => false,
            'sequence' => 0,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $thermalTelemetryTopic->id,
            'key' => 'temp_c',
        ], [
            'label' => 'Temperature (°C)',
            'json_path' => '$.status.temp',
            'type' => ParameterDataType::Decimal,
            'unit' => 'Celsius',
            'required' => true,
            'is_critical' => true,
            'validation_rules' => ['min' => -40, 'max' => 85],
            'validation_error_code' => 'TEMP_RANGE',
            'mutation_expression' => [
                '+' => [
                    ['*' => [
                        ['var' => 'val'],
                        1.8,
                    ]],
                    32,
                ],
            ],
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $thermalTelemetryTopic->id,
            'key' => 'humidity',
        ], [
            'label' => 'Humidity',
            'json_path' => 'status.humidity',
            'type' => ParameterDataType::Decimal,
            'unit' => 'Percent',
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => 0, 'max' => 100],
            'validation_error_code' => 'HUMIDITY_RANGE',
            'sequence' => 2,
            'is_active' => true,
        ]);

        DerivedParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $thermalVersion->id,
            'key' => 'heat_index',
        ], [
            'label' => 'Heat Index',
            'data_type' => ParameterDataType::Decimal,
            'unit' => 'Celsius',
            'expression' => [
                '+' => [
                    ['var' => 'temp_c'],
                    ['/' => [
                        ['var' => 'humidity'],
                        10,
                    ]],
                ],
            ],
            'dependencies' => ['temp_c', 'humidity'],
            'json_path' => 'computed.heat_index',
        ]);

        $networkSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $networkDeviceType->id,
            'name' => 'Network Gateway Contract',
        ]);

        $networkVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $networkSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Initial network telemetry contract',
        ]);

        $networkTelemetryTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $networkVersion->id,
            'key' => 'telemetry',
        ], [
            'label' => 'Telemetry',
            'direction' => TopicDirection::Publish,
            'suffix' => 'telemetry',
            'description' => 'Network signal and uptime data',
            'qos' => 1,
            'retain' => false,
            'sequence' => 0,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $networkTelemetryTopic->id,
            'key' => 'signal_strength',
        ], [
            'label' => 'Signal Strength',
            'json_path' => 'radio.signal',
            'type' => ParameterDataType::Integer,
            'unit' => 'dBm',
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => -120, 'max' => 0],
            'validation_error_code' => 'SIGNAL_RANGE',
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $networkTelemetryTopic->id,
            'key' => 'uptime_seconds',
        ], [
            'label' => 'Uptime (seconds)',
            'json_path' => '$.system.uptime',
            'type' => ParameterDataType::Integer,
            'unit' => 'Seconds',
            'required' => false,
            'is_critical' => false,
            'validation_rules' => ['min' => 0],
            'validation_error_code' => 'UPTIME_RANGE',
            'sequence' => 2,
            'is_active' => true,
        ]);

        $energySchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $energyMeterType->id,
            'name' => 'Energy Meter Contract',
        ]);

        $energyVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $energySchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Initial energy meter contract',
        ]);

        $energyTelemetryTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'telemetry',
        ], [
            'label' => 'Telemetry',
            'direction' => TopicDirection::Publish,
            'suffix' => 'telemetry',
            'description' => 'Voltage and power readings',
            'qos' => 1,
            'retain' => false,
            'sequence' => 0,
        ]);

        foreach (['V1', 'V2', 'V3'] as $index => $key) {
            ParameterDefinition::firstOrCreate([
                'schema_version_topic_id' => $energyTelemetryTopic->id,
                'key' => $key,
            ], [
                'label' => "Voltage {$key}",
                'json_path' => "voltages.{$key}",
                'type' => ParameterDataType::Decimal,
                'unit' => 'Volts',
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0, 'max' => 480],
                'validation_error_code' => 'VOLTAGE_RANGE',
                'sequence' => $index + 1,
                'is_active' => true,
            ]);
        }

        foreach (['power_l1', 'power_l2', 'power_l3'] as $index => $key) {
            ParameterDefinition::firstOrCreate([
                'schema_version_topic_id' => $energyTelemetryTopic->id,
                'key' => $key,
            ], [
                'label' => "Power {$key}",
                'json_path' => "power.{$key}",
                'type' => ParameterDataType::Decimal,
                'unit' => 'Watts',
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0],
                'validation_error_code' => 'POWER_RANGE',
                'sequence' => $index + 4,
                'is_active' => true,
            ]);
        }

        DerivedParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'avg_voltage',
        ], [
            'label' => 'Average Voltage',
            'data_type' => ParameterDataType::Decimal,
            'unit' => 'Volts',
            'expression' => [
                '/' => [
                    ['+' => [
                        ['var' => 'V1'],
                        ['var' => 'V2'],
                        ['var' => 'V3'],
                    ]],
                    3,
                ],
            ],
            'dependencies' => ['V1', 'V2', 'V3'],
            'json_path' => 'computed.avg_voltage',
        ]);

        DerivedParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'total_power',
        ], [
            'label' => 'Total Power',
            'data_type' => ParameterDataType::Decimal,
            'unit' => 'Watts',
            'expression' => [
                '+' => [
                    ['var' => 'power_l1'],
                    ['var' => 'power_l2'],
                    ['var' => 'power_l3'],
                ],
            ],
            'dependencies' => ['power_l1', 'power_l2', 'power_l3'],
            'json_path' => 'computed.total_power',
        ]);

        $this->seedSmartFan();
    }

    /**
     * Seed a smart fan device type with both publish (telemetry) and subscribe (command) topics.
     */
    private function seedSmartFan(): void
    {
        $smartFanType = DeviceType::firstOrCreate(
            ['key' => 'smart_fan'],
            [
                'organization_id' => null,
                'name' => 'Smart Fan',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'smart_fan',
                    password: 'fan_password',
                    useTls: false,
                    baseTopic: 'devices/fan',
                ))->toArray(),
            ]
        );

        $fanSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $smartFanType->id,
            'name' => 'Smart Fan Contract',
        ]);

        $fanVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $fanSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Smart fan with telemetry and command topics',
        ]);

        $fanStatusTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $fanVersion->id,
            'key' => 'fan_status',
        ], [
            'label' => 'Fan Status',
            'direction' => TopicDirection::Publish,
            'suffix' => 'status',
            'description' => 'Fan telemetry: speed, light state, and operating mode',
            'qos' => 1,
            'retain' => true,
            'sequence' => 0,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanStatusTopic->id,
            'key' => 'fan_speed',
        ], [
            'label' => 'Fan Speed',
            'json_path' => 'fan_speed',
            'type' => ParameterDataType::Integer,
            'unit' => 'RPM',
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => 0, 'max' => 100],
            'validation_error_code' => 'FAN_SPEED_RANGE',
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanStatusTopic->id,
            'key' => 'light_state',
        ], [
            'label' => 'Light State',
            'json_path' => 'light_state',
            'type' => ParameterDataType::Boolean,
            'required' => true,
            'is_critical' => false,
            'sequence' => 2,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanStatusTopic->id,
            'key' => 'mode',
        ], [
            'label' => 'Operating Mode',
            'json_path' => 'mode',
            'type' => ParameterDataType::String,
            'required' => false,
            'is_critical' => false,
            'validation_rules' => ['enum' => ['cooling', 'heating', 'auto']],
            'validation_error_code' => 'INVALID_MODE',
            'sequence' => 3,
            'is_active' => true,
        ]);

        $fanControlTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $fanVersion->id,
            'key' => 'fan_control',
        ], [
            'label' => 'Fan Control',
            'direction' => TopicDirection::Subscribe,
            'suffix' => 'control',
            'description' => 'Command topic to control fan speed, light, and mode',
            'qos' => 1,
            'retain' => false,
            'sequence' => 1,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanControlTopic->id,
            'key' => 'fan_speed',
        ], [
            'label' => 'Fan Speed',
            'json_path' => 'fan_speed',
            'type' => ParameterDataType::Integer,
            'required' => true,
            'is_critical' => false,
            'default_value' => 0,
            'validation_rules' => ['min' => 0, 'max' => 100],
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanControlTopic->id,
            'key' => 'light_state',
        ], [
            'label' => 'Light State',
            'json_path' => 'light_state',
            'type' => ParameterDataType::Boolean,
            'required' => true,
            'is_critical' => false,
            'default_value' => false,
            'sequence' => 2,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanControlTopic->id,
            'key' => 'mode',
        ], [
            'label' => 'Operating Mode',
            'json_path' => 'mode',
            'type' => ParameterDataType::String,
            'required' => false,
            'is_critical' => false,
            'default_value' => 'auto',
            'validation_rules' => ['enum' => ['cooling', 'heating', 'auto']],
            'sequence' => 3,
            'is_active' => true,
        ]);
    }
}
