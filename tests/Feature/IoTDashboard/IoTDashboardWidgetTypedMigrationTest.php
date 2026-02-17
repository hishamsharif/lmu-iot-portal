<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function typedDashboardMigration(): Migration
{
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_02_15_232257_refactor_iot_dashboard_widgets_to_typed_config.php');

    return $migration;
}

function createLegacyWidgetForeignKeys(): array
{
    $organization = Organization::factory()->create();
    $dashboard = IoTDashboard::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $deviceType = DeviceType::factory()->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
    ]);
    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    return [$dashboard, $topic, $device];
}

it('migrates legacy widget columns into typed config and layout deterministically', function (): void {
    $migration = typedDashboardMigration();
    $migration->down();

    [$dashboard, $topic, $device] = createLegacyWidgetForeignKeys();

    $widgetId = DB::table('iot_dashboard_widgets')->insertGetId([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $device->id,
        'schema_version_topic_id' => $topic->id,
        'type' => 'line_chart',
        'title' => 'Legacy Line Widget',
        'series_config' => json_encode([
            ['key' => 'V1', 'label' => 'Voltage V1', 'color' => '#22d3ee'],
            ['key' => 'V2', 'label' => 'Voltage V2', 'color' => '#a855f7'],
        ], JSON_THROW_ON_ERROR),
        'options' => json_encode([
            'layout' => [
                'x' => 2,
                'y' => 1,
                'w' => 6,
                'h' => 4,
            ],
            'layout_columns' => 24,
            'card_height_px' => 384,
        ], JSON_THROW_ON_ERROR),
        'use_websocket' => true,
        'use_polling' => true,
        'polling_interval_seconds' => 10,
        'lookback_minutes' => 120,
        'max_points' => 240,
        'sequence' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    $row = DB::table('iot_dashboard_widgets')->where('id', $widgetId)->first();

    $config = json_decode((string) $row->config, true, 512, JSON_THROW_ON_ERROR);
    $layout = json_decode((string) $row->layout, true, 512, JSON_THROW_ON_ERROR);

    expect(data_get($config, 'series.0.key'))->toBe('V1')
        ->and(data_get($config, 'series.1.key'))->toBe('V2')
        ->and(data_get($config, 'transport.use_websocket'))->toBeTrue()
        ->and(data_get($config, 'transport.use_polling'))->toBeTrue()
        ->and(data_get($config, 'transport.polling_interval_seconds'))->toBe(10)
        ->and(data_get($config, 'window.lookback_minutes'))->toBe(120)
        ->and(data_get($config, 'window.max_points'))->toBe(240)
        ->and(data_get($layout, 'x'))->toBe(2)
        ->and(data_get($layout, 'y'))->toBe(1)
        ->and(data_get($layout, 'w'))->toBe(6)
        ->and(data_get($layout, 'h'))->toBe(4)
        ->and(data_get($layout, 'columns'))->toBe(24)
        ->and(data_get($layout, 'card_height_px'))->toBe(384);
});

it('fails migration for unsupported legacy widget types with an actionable error', function (): void {
    $migration = typedDashboardMigration();
    $migration->down();

    [$dashboard, $topic, $device] = createLegacyWidgetForeignKeys();

    DB::table('iot_dashboard_widgets')->insert([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $device->id,
        'schema_version_topic_id' => $topic->id,
        'type' => 'custom_chart',
        'title' => 'Unsupported Legacy Widget',
        'series_config' => json_encode([
            ['key' => 'value', 'label' => 'Value', 'color' => '#22d3ee'],
        ], JSON_THROW_ON_ERROR),
        'options' => json_encode([], JSON_THROW_ON_ERROR),
        'use_websocket' => true,
        'use_polling' => true,
        'polling_interval_seconds' => 10,
        'lookback_minutes' => 120,
        'max_points' => 240,
        'sequence' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'unsupported type');
});
