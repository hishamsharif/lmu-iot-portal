<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Seeder;

class IoTDashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::query()->orderBy('id')->first();

        if (! $organization instanceof Organization) {
            $this->command?->warn('No organization found. Skipping IoT dashboard seed.');

            return;
        }

        $telemetryTopic = SchemaVersionTopic::query()
            ->where('direction', TopicDirection::Publish->value)
            ->where('key', 'telemetry')
            ->whereHas('schemaVersion.schema.deviceType', fn ($query) => $query->where('key', 'energy_meter'))
            ->orderBy('id')
            ->first();

        if (! $telemetryTopic instanceof SchemaVersionTopic) {
            $this->command?->warn('Energy meter telemetry topic not found. Run DeviceSchemaSeeder first.');

            return;
        }

        $dashboard = IoTDashboard::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => 'energy-meter-dashboard',
            ],
            [
                'name' => 'Energy Meter Dashboard',
                'description' => 'Realtime and historical voltage monitoring for the main energy meter.',
                'refresh_interval_seconds' => 10,
                'is_active' => true,
            ],
        );

        $device = Device::query()
            ->where('organization_id', $organization->id)
            ->where('device_schema_version_id', $telemetryTopic->device_schema_version_id)
            ->orderBy('id')
            ->first();

        if (! $device instanceof Device) {
            $this->command?->warn('No compatible device found for energy meter telemetry topic. Skipping widget seed.');

            return;
        }

        $parameterLabels = ParameterDefinition::query()
            ->where('schema_version_topic_id', $telemetryTopic->id)
            ->whereIn('key', ['V1', 'V2', 'V3'])
            ->pluck('label', 'key')
            ->all();

        $seriesConfiguration = [
            [
                'key' => 'V1',
                'label' => (string) ($parameterLabels['V1'] ?? 'V1'),
                'color' => '#22d3ee',
            ],
            [
                'key' => 'V2',
                'label' => (string) ($parameterLabels['V2'] ?? 'V2'),
                'color' => '#a855f7',
            ],
            [
                'key' => 'V3',
                'label' => (string) ($parameterLabels['V3'] ?? 'V3'),
                'color' => '#f97316',
            ],
        ];

        IoTDashboardWidget::query()->updateOrCreate(
            [
                'iot_dashboard_id' => $dashboard->id,
                'schema_version_topic_id' => $telemetryTopic->id,
                'title' => 'Energy Meter Voltages (V1 / V2 / V3)',
            ],
            [
                'type' => 'line_chart',
                'device_id' => $device->id,
                'series_config' => $seriesConfiguration,
                'options' => [
                    'layout' => [
                        'x' => 0,
                        'y' => 0,
                        'w' => 12,
                        'h' => 4,
                    ],
                    'layout_columns' => 24,
                    'grid_columns' => 12,
                    'card_height_px' => 360,
                ],
                'use_websocket' => true,
                'use_polling' => true,
                'polling_interval_seconds' => 10,
                'lookback_minutes' => 120,
                'max_points' => 240,
                'sequence' => 1,
            ],
        );
    }
}
