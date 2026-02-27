<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\DeviceSchema\Enums\MetricUnit;
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
        $thermalDeviceType = $this->upsertMqttDeviceType(
            key: 'thermal_sensor',
            name: 'Thermal Sensor',
            username: 'thermal_sensor',
            password: 'thermal_password',
            baseTopic: 'thermal',
        );

        $networkDeviceType = $this->upsertMqttDeviceType(
            key: 'network_gateway',
            name: 'Network Gateway',
            username: 'network_gateway',
            password: 'network_password',
            baseTopic: 'network',
        );

        $energyMeterType = $this->upsertMqttDeviceType(
            key: 'energy_meter',
            name: 'Energy Meter',
            username: 'energy_meter',
            password: 'energy_password',
            baseTopic: 'energy',
        );

        $singlePhaseEnergyMeterType = $this->upsertMqttDeviceType(
            key: 'single_phase_energy_meter',
            name: 'Single-Phase Energy Meter',
            username: 'single_phase_meter',
            password: 'single_phase_password',
            baseTopic: 'energy/single-phase',
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
            'description' => 'Three-phase voltages and currents with cumulative energy readings',
            'qos' => 1,
            'retain' => false,
            'sequence' => 0,
        ]);

        ParameterDefinition::query()
            ->where('schema_version_topic_id', $energyTelemetryTopic->id)
            ->whereIn('key', ['power_l1', 'power_l2', 'power_l3'])
            ->delete();

        DerivedParameterDefinition::query()
            ->where('device_schema_version_id', $energyVersion->id)
            ->where('key', 'total_power')
            ->delete();

        foreach (['V1', 'V2', 'V3'] as $index => $key) {
            ParameterDefinition::updateOrCreate([
                'schema_version_topic_id' => $energyTelemetryTopic->id,
                'key' => $key,
            ], [
                'label' => "Voltage {$key}",
                'json_path' => "voltages.{$key}",
                'type' => ParameterDataType::Decimal,
                'unit' => MetricUnit::Volts->value,
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 180, 'max' => 280, 'category' => 'static'],
                'validation_error_code' => 'VOLTAGE_RANGE',
                'sequence' => $index + 1,
                'is_active' => true,
            ]);
        }

        foreach (['A1', 'A2', 'A3'] as $index => $key) {
            ParameterDefinition::updateOrCreate([
                'schema_version_topic_id' => $energyTelemetryTopic->id,
                'key' => $key,
            ], [
                'label' => "Current {$key}",
                'json_path' => "currents.{$key}",
                'type' => ParameterDataType::Decimal,
                'unit' => MetricUnit::Amperes->value,
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0, 'max' => 120, 'category' => 'static'],
                'validation_error_code' => 'CURRENT_RANGE',
                'sequence' => $index + 4,
                'is_active' => true,
            ]);
        }

        ParameterDefinition::updateOrCreate([
            'schema_version_topic_id' => $energyTelemetryTopic->id,
            'key' => 'total_energy_kwh',
        ], [
            'label' => 'Total Energy',
            'json_path' => 'energy.total_energy_kwh',
            'type' => ParameterDataType::Decimal,
            'unit' => MetricUnit::KilowattHours->value,
            'required' => true,
            'is_critical' => true,
            'validation_rules' => [
                'min' => 0,
                'category' => 'counter',
                'increment_min' => 0.08,
                'increment_max' => 0.45,
            ],
            'validation_error_code' => 'ENERGY_COUNTER_RANGE',
            'sequence' => 7,
            'is_active' => true,
        ]);

        ParameterDefinition::updateOrCreate([
            'schema_version_topic_id' => $energyTelemetryTopic->id,
            'key' => 'meter_state',
        ], [
            'label' => 'Meter State',
            'json_path' => 'status.meter_state',
            'type' => ParameterDataType::String,
            'required' => true,
            'is_critical' => false,
            'validation_rules' => [
                'category' => 'enum',
                'enum' => ['idle', 'normal', 'fault'],
            ],
            'validation_error_code' => 'METER_STATE_INVALID',
            'sequence' => 8,
            'is_active' => true,
        ]);

        DerivedParameterDefinition::updateOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'avg_voltage',
        ], [
            'label' => 'Average Voltage',
            'data_type' => ParameterDataType::Decimal,
            'unit' => MetricUnit::Volts->value,
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

        DerivedParameterDefinition::updateOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'total_current',
        ], [
            'label' => 'Total Current',
            'data_type' => ParameterDataType::Decimal,
            'unit' => MetricUnit::Amperes->value,
            'expression' => [
                '+' => [
                    ['var' => 'A1'],
                    ['var' => 'A2'],
                    ['var' => 'A3'],
                ],
            ],
            'dependencies' => ['A1', 'A2', 'A3'],
            'json_path' => 'computed.total_current',
        ]);

        $this->seedSinglePhaseEnergyMeter($singlePhaseEnergyMeterType);
        $this->seedSmartFan();
        $this->seedDimmableLight();
        $this->seedRgbLedController();
    }

    private function seedSinglePhaseEnergyMeter(DeviceType $singlePhaseEnergyMeterType): void
    {
        $singlePhaseSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $singlePhaseEnergyMeterType->id,
            'name' => 'Single-Phase Energy Meter Contract',
        ]);

        $singlePhaseVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $singlePhaseSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Single-phase PZEM RS485 telemetry contract',
        ]);

        $this->upsertFirmwareTemplate(
            version: $singlePhaseVersion,
            template: $this->replaceDeviceIdPlaceholder(
                $this->loadFirmwareTemplate('plan/DeviceControlArchitecture/esp32-single-phase-energy-meter/esp32-single-phase-energy-meter.ino'),
                'single-phase-energy-meter-01',
            ),
        );

        $telemetryTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $singlePhaseVersion->id,
            'key' => 'telemetry',
        ], [
            'label' => 'Telemetry',
            'direction' => TopicDirection::Publish,
            'purpose' => TopicPurpose::Telemetry,
            'suffix' => 'telemetry',
            'description' => 'Single-phase PZEM meter telemetry and diagnostics',
            'qos' => 1,
            'retain' => false,
            'sequence' => 0,
        ]);

        $stateTopic = SchemaVersionTopic::firstOrCreate([
            'device_schema_version_id' => $singlePhaseVersion->id,
            'key' => 'state',
        ], [
            'label' => 'State',
            'direction' => TopicDirection::Publish,
            'purpose' => TopicPurpose::State,
            'suffix' => 'state',
            'description' => 'Meter read health and diagnostics snapshot',
            'qos' => 1,
            'retain' => true,
            'sequence' => 1,
        ]);

        $telemetryParameters = [
            [
                'key' => 'voltage_v',
                'label' => 'Voltage',
                'json_path' => 'voltage_v',
                'type' => ParameterDataType::Decimal,
                'unit' => MetricUnit::Volts->value,
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 90, 'max' => 280],
                'validation_error_code' => 'VOLTAGE_RANGE',
                'sequence' => 1,
            ],
            [
                'key' => 'current_a',
                'label' => 'Current',
                'json_path' => 'current_a',
                'type' => ParameterDataType::Decimal,
                'unit' => MetricUnit::Amperes->value,
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0, 'max' => 120],
                'validation_error_code' => 'CURRENT_RANGE',
                'sequence' => 2,
            ],
            [
                'key' => 'active_power_w',
                'label' => 'Active Power',
                'json_path' => 'active_power_w',
                'type' => ParameterDataType::Decimal,
                'unit' => MetricUnit::Watts->value,
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0, 'max' => 30000],
                'validation_error_code' => 'ACTIVE_POWER_RANGE',
                'sequence' => 3,
            ],
            [
                'key' => 'frequency_hz',
                'label' => 'Frequency',
                'json_path' => 'frequency_hz',
                'type' => ParameterDataType::Decimal,
                'unit' => 'Hz',
                'required' => true,
                'is_critical' => false,
                'validation_rules' => ['min' => 45, 'max' => 65],
                'validation_error_code' => 'FREQUENCY_RANGE',
                'sequence' => 4,
            ],
            [
                'key' => 'power_factor',
                'label' => 'Power Factor',
                'json_path' => 'power_factor',
                'type' => ParameterDataType::Decimal,
                'required' => true,
                'is_critical' => false,
                'validation_rules' => ['min' => 0, 'max' => 1],
                'validation_error_code' => 'POWER_FACTOR_RANGE',
                'sequence' => 5,
            ],
            [
                'key' => 'total_energy_kwh',
                'label' => 'Total Energy',
                'json_path' => 'total_energy_kwh',
                'type' => ParameterDataType::Decimal,
                'unit' => MetricUnit::KilowattHours->value,
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0, 'category' => 'counter'],
                'validation_error_code' => 'ENERGY_COUNTER_RANGE',
                'sequence' => 6,
            ],
            [
                'key' => 'alarm_status',
                'label' => 'Alarm Status',
                'json_path' => 'alarm_status',
                'type' => ParameterDataType::Integer,
                'required' => false,
                'is_critical' => false,
                'validation_rules' => ['min' => 0, 'max' => 1],
                'validation_error_code' => 'ALARM_STATUS_RANGE',
                'sequence' => 7,
            ],
            [
                'key' => 'read_ok',
                'label' => 'Read OK',
                'json_path' => 'read_ok',
                'type' => ParameterDataType::Boolean,
                'required' => true,
                'is_critical' => false,
                'sequence' => 8,
            ],
            [
                'key' => 'modbus_error',
                'label' => 'Modbus Error',
                'json_path' => 'modbus_error',
                'type' => ParameterDataType::String,
                'required' => false,
                'is_critical' => false,
                'sequence' => 9,
            ],
            [
                'key' => 'poll_ms',
                'label' => 'Poll Duration',
                'json_path' => 'poll_ms',
                'type' => ParameterDataType::Integer,
                'required' => false,
                'is_critical' => false,
                'validation_rules' => ['min' => 0, 'max' => 10000],
                'validation_error_code' => 'POLL_DURATION_RANGE',
                'sequence' => 10,
            ],
        ];

        foreach ($telemetryParameters as $parameter) {
            ParameterDefinition::updateOrCreate([
                'schema_version_topic_id' => $telemetryTopic->id,
                'key' => $parameter['key'],
            ], [
                'label' => $parameter['label'],
                'json_path' => $parameter['json_path'],
                'type' => $parameter['type'],
                'unit' => $parameter['unit'] ?? null,
                'required' => $parameter['required'],
                'is_critical' => $parameter['is_critical'],
                'validation_rules' => $parameter['validation_rules'] ?? null,
                'validation_error_code' => $parameter['validation_error_code'] ?? null,
                'sequence' => $parameter['sequence'],
                'is_active' => true,
            ]);
        }

        $stateParameters = [
            [
                'key' => 'read_ok',
                'label' => 'Read OK',
                'json_path' => 'read_ok',
                'type' => ParameterDataType::Boolean,
                'required' => true,
                'is_critical' => false,
                'sequence' => 1,
            ],
            [
                'key' => 'modbus_error',
                'label' => 'Modbus Error',
                'json_path' => 'modbus_error',
                'type' => ParameterDataType::String,
                'required' => false,
                'is_critical' => false,
                'sequence' => 2,
            ],
            [
                'key' => 'poll_ms',
                'label' => 'Poll Duration',
                'json_path' => 'poll_ms',
                'type' => ParameterDataType::Integer,
                'required' => false,
                'is_critical' => false,
                'validation_rules' => ['min' => 0, 'max' => 10000],
                'validation_error_code' => 'POLL_DURATION_RANGE',
                'sequence' => 3,
            ],
            [
                'key' => 'fw_version',
                'label' => 'Firmware Version',
                'json_path' => 'fw_version',
                'type' => ParameterDataType::String,
                'required' => false,
                'is_critical' => false,
                'sequence' => 4,
            ],
        ];

        foreach ($stateParameters as $parameter) {
            ParameterDefinition::updateOrCreate([
                'schema_version_topic_id' => $stateTopic->id,
                'key' => $parameter['key'],
            ], [
                'label' => $parameter['label'],
                'json_path' => $parameter['json_path'],
                'type' => $parameter['type'],
                'required' => $parameter['required'],
                'is_critical' => $parameter['is_critical'],
                'validation_rules' => $parameter['validation_rules'] ?? null,
                'validation_error_code' => $parameter['validation_error_code'] ?? null,
                'sequence' => $parameter['sequence'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Seed a smart fan device type with both publish (telemetry) and subscribe (command) topics.
     */
    private function seedSmartFan(): void
    {
        $smartFanType = $this->upsertMqttDeviceType(
            key: 'smart_fan',
            name: 'Smart Fan',
            username: 'smart_fan',
            password: 'fan_password',
            baseTopic: 'devices/fan',
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
        $dimmableLightType = $this->upsertMqttDeviceType(
            key: 'dimmable_light',
            name: 'Dimmable Light',
            username: 'dimmable_light',
            password: 'light_password',
            baseTopic: 'devices/dimmable-light',
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
        $rgbLedType = $this->upsertMqttDeviceType(
            key: 'rgb_led_controller',
            name: 'RGB LED Controller',
            username: 'rgb_led',
            password: 'rgb_password',
            baseTopic: 'devices/rgb-led',
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

    private function upsertMqttDeviceType(
        string $key,
        string $name,
        string $username,
        string $password,
        string $baseTopic,
    ): DeviceType {
        return DeviceType::updateOrCreate(
            ['key' => $key],
            [
                'organization_id' => null,
                'name' => $name,
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: $this->resolveSeedBrokerHost(),
                    brokerPort: $this->resolveSeedBrokerPort(),
                    username: $username,
                    password: $password,
                    useTls: false,
                    baseTopic: $baseTopic,
                ))->toArray(),
            ],
        );
    }

    private function resolveSeedBrokerHost(): string
    {
        $configuredMqttHost = config('iot.mqtt.host');
        if (is_string($configuredMqttHost) && trim($configuredMqttHost) !== '') {
            return trim($configuredMqttHost);
        }

        $configuredNatsHost = config('iot.nats.host');
        if (is_string($configuredNatsHost) && trim($configuredNatsHost) !== '') {
            return trim($configuredNatsHost);
        }

        return '127.0.0.1';
    }

    private function resolveSeedBrokerPort(): int
    {
        $configuredMqttPort = config('iot.mqtt.port', 1883);

        return is_numeric($configuredMqttPort)
            ? (int) $configuredMqttPort
            : 1883;
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
