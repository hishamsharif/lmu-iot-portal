<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Pages\IoTDashboard as IoTDashboardPage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDashboardTopicForPageTest(): array
{
    $organization = Organization::factory()->create();
    $dashboard = IoTDashboard::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Main Energy Dashboard',
        'slug' => 'main-energy-dashboard',
    ]);
    $deviceType = DeviceType::factory()->mqtt()->create(['key' => 'energy_meter_page_test']);
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'label' => 'Telemetry',
        'suffix' => 'telemetry',
    ]);
    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'name' => 'Main Energy Meter',
    ]);

    foreach (['V1', 'V2', 'V3'] as $sequence => $key) {
        ParameterDefinition::factory()->create([
            'schema_version_topic_id' => $topic->id,
            'key' => $key,
            'label' => "Voltage {$key}",
            'json_path' => "voltages.{$key}",
            'type' => ParameterDataType::Decimal,
            'required' => true,
            'is_active' => true,
            'sequence' => $sequence + 1,
            'mutation_expression' => null,
            'validation_error_code' => null,
        ]);
    }

    return [$dashboard, $topic, $device];
}

beforeEach(function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);
});

it('renders the IoT dashboard page', function (): void {
    [$dashboard] = createDashboardTopicForPageTest();

    livewire(IoTDashboardPage::class)
        ->set('dashboardId', $dashboard->id)
        ->assertSuccessful()
        ->assertSee('Main Energy Dashboard');
});

it('adds a line widget with three configured series', function (): void {
    [$dashboard, $topic, $device] = createDashboardTopicForPageTest();

    livewire(IoTDashboardPage::class)
        ->set('dashboardId', $dashboard->id)
        ->callAction('addLineWidget', data: [
            'title' => 'Energy Meter Voltages',
            'schema_version_topic_id' => (string) $topic->id,
            'device_id' => (string) $device->id,
            'parameter_keys' => ['V1', 'V2', 'V3'],
            'use_websocket' => true,
            'use_polling' => true,
            'polling_interval_seconds' => 10,
            'lookback_minutes' => 120,
            'max_points' => 240,
            'grid_columns' => '1',
            'card_height_px' => 360,
        ])
        ->assertNotified('Line widget added');

    $widget = IoTDashboardWidget::query()
        ->where('iot_dashboard_id', $dashboard->id)
        ->where('title', 'Energy Meter Voltages')
        ->first();

    expect($widget)->not->toBeNull()
        ->and($widget?->type)->toBe('line_chart')
        ->and($widget?->device_id)->toBe($device->id)
        ->and(collect($widget?->series_config ?? [])->pluck('key')->all())->toBe(['V1', 'V2', 'V3']);
});

it('edits an existing line widget using action arguments', function (): void {
    [$dashboard, $topic, $device] = createDashboardTopicForPageTest();

    $widget = IoTDashboardWidget::factory()->create([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $device->id,
        'schema_version_topic_id' => $topic->id,
        'title' => 'Old Title',
        'series_config' => [
            ['key' => 'V1', 'label' => 'Voltage V1', 'color' => '#22d3ee'],
        ],
    ]);

    livewire(IoTDashboardPage::class)
        ->set('dashboardId', $dashboard->id)
        ->callAction(
            TestAction::make('editWidget')->arguments(['widget' => $widget->id]),
            data: [
                'title' => 'Updated Voltages',
                'device_id' => (string) $device->id,
                'schema_version_topic_id' => (string) $topic->id,
                'parameter_keys' => ['V1', 'V2', 'V3'],
                'use_websocket' => true,
                'use_polling' => true,
                'polling_interval_seconds' => 8,
                'lookback_minutes' => 90,
                'max_points' => 300,
                'grid_columns' => '6',
                'card_height_px' => 420,
            ],
        )
        ->assertNotified('Widget updated');

    $widget->refresh();

    expect($widget->title)->toBe('Updated Voltages')
        ->and($widget->polling_interval_seconds)->toBe(8)
        ->and(collect($widget->series_config)->pluck('key')->all())->toBe(['V1', 'V2', 'V3']);
});

it('loads gridstack extra stylesheet for multi-column widget widths', function (): void {
    [$dashboard] = createDashboardTopicForPageTest();

    $this->get(route('filament.admin.pages.io-t-dashboard', ['dashboard' => $dashboard->id]))
        ->assertSuccessful()
        ->assertSee('gridstack-extra.min.css', escape: false);
});
