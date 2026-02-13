<?php

declare(strict_types=1);

namespace Database\Factories\Domain\IoTDashboard\Models;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\IoTDashboard\Models\IoTDashboardWidget>
 */
class IoTDashboardWidgetFactory extends Factory
{
    protected $model = IoTDashboardWidget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iot_dashboard_id' => IoTDashboardFactory::new(),
            'device_id' => Device::factory(),
            'schema_version_topic_id' => SchemaVersionTopic::factory()->publish(),
            'type' => 'line_chart',
            'title' => Str::title($this->faker->words(3, true)),
            'series_config' => [
                ['key' => 'value', 'label' => 'Value', 'color' => '#38bdf8'],
            ],
            'options' => [
                'layout' => [
                    'x' => 0,
                    'y' => 0,
                    'w' => 6,
                    'h' => 4,
                ],
                'layout_columns' => 24,
                'grid_columns' => 6,
                'card_height_px' => 360,
            ],
            'use_websocket' => true,
            'use_polling' => true,
            'polling_interval_seconds' => 10,
            'lookback_minutes' => 120,
            'max_points' => 240,
            'sequence' => 0,
        ];
    }
}
