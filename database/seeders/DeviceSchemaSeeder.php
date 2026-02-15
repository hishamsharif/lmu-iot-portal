<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Enums\TopicPurpose;
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
            'purpose' => TopicPurpose::Telemetry,
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
            'purpose' => TopicPurpose::Telemetry,
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
            'purpose' => TopicPurpose::Telemetry,
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
        $this->seedDimmableLight();
        $this->seedRgbLedController();
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

        $fanStateTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $fanVersion->id,
            'key' => 'fan_state',
        ], [
            'label' => 'Fan State',
            'direction' => TopicDirection::Publish,
            'purpose' => TopicPurpose::State,
            'suffix' => 'state',
            'description' => 'Fan telemetry: speed, light state, and operating mode',
            'qos' => 1,
            'retain' => true,
            'sequence' => 0,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $fanStateTopic->id,
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
            'schema_version_topic_id' => $fanStateTopic->id,
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
            'schema_version_topic_id' => $fanStateTopic->id,
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
            'purpose' => TopicPurpose::Command,
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
            'control_ui' => ['widget' => 'slider', 'min' => 0, 'max' => 100, 'step' => 1],
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
            'control_ui' => ['widget' => 'toggle'],
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
            'control_ui' => ['widget' => 'select'],
            'sequence' => 3,
            'is_active' => true,
        ]);
    }

    /**
     * Seed a dimmable light device type with both status (publish) and control (subscribe) topics.
     */
    private function seedDimmableLight(): void
    {
        $dimmableLightType = DeviceType::firstOrCreate(
            ['key' => 'dimmable_light'],
            [
                'organization_id' => null,
                'name' => 'Dimmable Light',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'dimmable_light',
                    password: 'light_password',
                    useTls: false,
                    baseTopic: 'devices/dimmable-light',
                ))->toArray(),
            ]
        );

        $lightSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $dimmableLightType->id,
            'name' => 'Dimmable Light Control Contract',
        ]);

        $lightVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $lightSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Dimmable light with brightness levels 0-10',
        ]);

        $this->upsertFirmwareTemplate(
            version: $lightVersion,
            template: $this->replaceDeviceIdPlaceholder(
                $this->loadFirmwareTemplate('plan/DeviceControlArchitecture/esp32-dimmable-light/esp32-dimmable-light.ino'),
                'dimmable-light-01',
            ),
        );

        // Publish topic: status reporting
        $lightStateTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $lightVersion->id,
            'key' => 'brightness_state',
        ], [
            'label' => 'Brightness State',
            'direction' => TopicDirection::Publish,
            'purpose' => TopicPurpose::State,
            'suffix' => 'state',
            'description' => 'Device brightness state reporting',
            'qos' => 1,
            'retain' => true,
            'sequence' => 0,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $lightStateTopic->id,
            'key' => 'brightness_level',
        ], [
            'label' => 'Brightness Level',
            'json_path' => 'brightness_level',
            'type' => ParameterDataType::Integer,
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => 0, 'max' => 10],
            'validation_error_code' => 'BRIGHTNESS_RANGE',
            'sequence' => 1,
            'is_active' => true,
        ]);

        // Subscribe topic: control commands
        $lightControlTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $lightVersion->id,
            'key' => 'brightness_control',
        ], [
            'label' => 'Brightness Control',
            'direction' => TopicDirection::Subscribe,
            'purpose' => TopicPurpose::Command,
            'suffix' => 'control',
            'description' => 'Command topic for brightness level (0-10)',
            'qos' => 1,
            'retain' => false,
            'sequence' => 1,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $lightControlTopic->id,
            'key' => 'brightness_level',
        ], [
            'label' => 'Brightness Level',
            'json_path' => 'brightness_level',
            'type' => ParameterDataType::Integer,
            'required' => true,
            'is_critical' => false,
            'default_value' => 0,
            'validation_rules' => ['min' => 0, 'max' => 10],
            'validation_error_code' => 'BRIGHTNESS_RANGE',
            'control_ui' => ['widget' => 'slider', 'min' => 0, 'max' => 10, 'step' => 1],
            'sequence' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Seed an RGB LED controller to showcase advanced controls (color picker + scenes + push button).
     */
    private function seedRgbLedController(): void
    {
        $rgbLedType = DeviceType::firstOrCreate(
            ['key' => 'rgb_led_controller'],
            [
                'organization_id' => null,
                'name' => 'RGB LED Controller',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'rgb_led',
                    password: 'rgb_password',
                    useTls: false,
                    baseTopic: 'devices/rgb-led',
                ))->toArray(),
            ]
        );

        $rgbSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $rgbLedType->id,
            'name' => 'RGB LED Control Contract',
        ]);

        $rgbVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $rgbSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'RGB LED with color picker, brightness, and effects',
        ]);

        $this->upsertFirmwareTemplate(
            version: $rgbVersion,
            template: $this->loadFirmwareTemplate('plan/DeviceControlArchitecture/esp32-rgb-light/esp-32-rgb-light.ino'),
        );

        $rgbStateTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $rgbVersion->id,
            'key' => 'lighting_state',
        ], [
            'label' => 'Lighting State',
            'direction' => TopicDirection::Publish,
            'purpose' => TopicPurpose::State,
            'suffix' => 'state',
            'description' => 'Reports current LED power, brightness, color, and effect',
            'qos' => 1,
            'retain' => true,
            'sequence' => 0,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbStateTopic->id,
            'key' => 'power',
        ], [
            'label' => 'Power',
            'json_path' => 'power',
            'type' => ParameterDataType::Boolean,
            'required' => true,
            'is_critical' => false,
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbStateTopic->id,
            'key' => 'brightness',
        ], [
            'label' => 'Brightness',
            'json_path' => 'brightness',
            'type' => ParameterDataType::Integer,
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => 0, 'max' => 100],
            'sequence' => 2,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbStateTopic->id,
            'key' => 'color_hex',
        ], [
            'label' => 'Color',
            'json_path' => 'color_hex',
            'type' => ParameterDataType::String,
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['regex' => '/^#([A-Fa-f0-9]{6})$/'],
            'sequence' => 3,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbStateTopic->id,
            'key' => 'effect',
        ], [
            'label' => 'Effect',
            'json_path' => 'effect',
            'type' => ParameterDataType::String,
            'required' => false,
            'is_critical' => false,
            'validation_rules' => ['enum' => ['solid', 'blink', 'breathe', 'rainbow']],
            'sequence' => 4,
            'is_active' => true,
        ]);

        $rgbControlTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $rgbVersion->id,
            'key' => 'lighting_control',
        ], [
            'label' => 'Lighting Control',
            'direction' => TopicDirection::Subscribe,
            'purpose' => TopicPurpose::Command,
            'suffix' => 'control',
            'description' => 'Command topic to control power, brightness, color, and effect',
            'qos' => 1,
            'retain' => false,
            'sequence' => 1,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbControlTopic->id,
            'key' => 'power',
        ], [
            'label' => 'Power',
            'json_path' => 'power',
            'type' => ParameterDataType::Boolean,
            'required' => true,
            'is_critical' => false,
            'default_value' => true,
            'control_ui' => ['widget' => 'toggle'],
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbControlTopic->id,
            'key' => 'brightness',
        ], [
            'label' => 'Brightness',
            'json_path' => 'brightness',
            'type' => ParameterDataType::Integer,
            'required' => true,
            'is_critical' => false,
            'default_value' => 50,
            'validation_rules' => ['min' => 0, 'max' => 100],
            'control_ui' => ['widget' => 'slider', 'min' => 0, 'max' => 100, 'step' => 1],
            'sequence' => 2,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbControlTopic->id,
            'key' => 'color_hex',
        ], [
            'label' => 'Color',
            'json_path' => 'color_hex',
            'type' => ParameterDataType::String,
            'required' => true,
            'is_critical' => false,
            'default_value' => '#ff0000',
            'validation_rules' => ['regex' => '/^#([A-Fa-f0-9]{6})$/'],
            'control_ui' => ['widget' => 'color', 'color_format' => 'hex'],
            'sequence' => 3,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'schema_version_topic_id' => $rgbControlTopic->id,
            'key' => 'effect',
        ], [
            'label' => 'Effect',
            'json_path' => 'effect',
            'type' => ParameterDataType::String,
            'required' => false,
            'is_critical' => false,
            'default_value' => 'solid',
            'validation_rules' => ['enum' => ['solid', 'blink', 'breathe', 'rainbow']],
            'control_ui' => ['widget' => 'select'],
            'sequence' => 4,
            'is_active' => true,
        ]);

        ParameterDefinition::query()
            ->where('schema_version_topic_id', $rgbControlTopic->id)
            ->where('key', 'apply_changes')
            ->delete();
    }

    private function upsertFirmwareTemplate(DeviceSchemaVersion $version, ?string $template): void
    {
        if (! is_string($template) || trim($template) === '') {
            return;
        }

        $version->update([
            'firmware_template' => $template,
        ]);
    }

    private function loadFirmwareTemplate(string $relativePath): ?string
    {
        $absolutePath = base_path($relativePath);

        if (! file_exists($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);

        if (! is_string($content)) {
            return null;
        }

        return $content;
    }

    private function replaceDeviceIdPlaceholder(?string $template, string $knownDeviceId): ?string
    {
        if (! is_string($template) || trim($template) === '') {
            return $template;
        }

        return str_replace($knownDeviceId, '{{DEVICE_ID}}', $template);
    }
}
