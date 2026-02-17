<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDashboardWidgetLayoutContext(): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);
    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
    ]);
    $dashboard = IoTDashboard::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $widget = IoTDashboardWidget::factory()->create([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $device->id,
        'schema_version_topic_id' => $topic->id,
    ]);

    return [$organization, $dashboard, $widget];
}

it('persists widget grid layout coordinates', function (): void {
    [, $dashboard, $widget] = createDashboardWidgetLayoutContext();

    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    $this->postJson(route('admin.iot-dashboard.dashboards.widgets.layout', [
        'dashboard' => $dashboard,
        'widget' => $widget,
    ]), [
        'x' => 1,
        'y' => 2,
        'w' => 24,
        'h' => 5,
    ])->assertOk()
        ->assertJsonPath('widget_id', $widget->id)
        ->assertJsonPath('layout.x', 1)
        ->assertJsonPath('layout.y', 2)
        ->assertJsonPath('layout.w', 24)
        ->assertJsonPath('layout.h', 5)
        ->assertJsonPath('layout.columns', 24)
        ->assertJsonPath('layout.card_height_px', 480);

    $widget->refresh();

    expect($widget->layoutArray()['x'])->toBe(1)
        ->and($widget->layoutArray()['y'])->toBe(2)
        ->and($widget->layoutArray()['w'])->toBe(24)
        ->and($widget->layoutArray()['h'])->toBe(5)
        ->and($widget->layoutArray()['columns'])->toBe(24)
        ->and($widget->layoutArray()['card_height_px'])->toBe(480);
});

it('forbids layout updates for users outside the organization', function (): void {
    [, $dashboard, $widget] = createDashboardWidgetLayoutContext();

    $user = User::factory()->create(['is_super_admin' => false]);
    $this->actingAs($user);

    $this->postJson(route('admin.iot-dashboard.dashboards.widgets.layout', [
        'dashboard' => $dashboard,
        'widget' => $widget,
    ]), [
        'x' => 0,
        'y' => 0,
        'w' => 2,
        'h' => 4,
    ])->assertForbidden();
});

it('allows layout updates for organization members', function (): void {
    [$organization, $dashboard, $widget] = createDashboardWidgetLayoutContext();

    $user = User::factory()->create(['is_super_admin' => false]);
    $user->organizations()->attach($organization->id);
    $this->actingAs($user);

    $this->postJson(route('admin.iot-dashboard.dashboards.widgets.layout', [
        'dashboard' => $dashboard,
        'widget' => $widget,
    ]), [
        'x' => 2,
        'y' => 3,
        'w' => 2,
        'h' => 4,
    ])->assertOk();
});
