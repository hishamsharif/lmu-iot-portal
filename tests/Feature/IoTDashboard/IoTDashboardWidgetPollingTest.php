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
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createDashboardWidgetContext(): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->mqtt()->create([
        'key' => 'energy_meter_widget_test',
    ]);
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create([
        'name' => 'Energy Meter Test Schema',
    ]);
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
        'version' => 1,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
        'label' => 'Telemetry',
    ]);

    foreach (['V1', 'V2', 'V3'] as $sequence => $key) {
        ParameterDefinition::factory()->create([
            'schema_version_topic_id' => $topic->id,
            'key' => $key,
            'label' => "Voltage {$key}",
            'json_path' => "voltages.{$key}",
            'type' => ParameterDataType::Decimal,
            'sequence' => $sequence + 1,
            'required' => true,
            'is_active' => true,
            'validation_rules' => ['min' => 0, 'max' => 480],
            'mutation_expression' => null,
            'validation_error_code' => null,
        ]);
    }

    $dashboard = IoTDashboard::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Energy Meter Polling',
        'slug' => 'energy-meter-polling',
    ]);
    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
    ]);
    $widget = IoTDashboardWidget::factory()->create([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $device->id,
        'schema_version_topic_id' => $topic->id,
        'type' => 'line_chart',
        'title' => 'Voltages',
        'series_config' => [
            ['key' => 'V1', 'label' => 'Voltage V1', 'color' => '#22d3ee'],
            ['key' => 'V2', 'label' => 'Voltage V2', 'color' => '#a855f7'],
            ['key' => 'V3', 'label' => 'Voltage V3', 'color' => '#f97316'],
        ],
        'lookback_minutes' => 240,
        'max_points' => 240,
    ]);

    return [$organization, $topic, $widget, $device];
}

it('returns configured line series points for polling', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    [, $topic, $widget, $device] = createDashboardWidgetContext();
    $otherDevice = Device::factory()->create([
        'organization_id' => $device->organization_id,
        'device_type_id' => $device->device_type_id,
        'device_schema_version_id' => $device->device_schema_version_id,
    ]);

    $baseTime = Carbon::parse('2026-02-13 09:00:00');

    DeviceTelemetryLog::factory()->forDevice($device)->forTopic($topic)->create([
        'recorded_at' => $baseTime->copy(),
        'transformed_values' => ['V1' => 228.5, 'V2' => 229.1, 'V3' => 230.4],
        'raw_payload' => ['voltages' => ['V1' => 228.5, 'V2' => 229.1, 'V3' => 230.4]],
        'validation_status' => ValidationStatus::Valid,
    ]);
    DeviceTelemetryLog::factory()->forDevice($device)->forTopic($topic)->create([
        'recorded_at' => $baseTime->copy()->addMinutes(5),
        'transformed_values' => ['V1' => 229.2, 'V2' => 230.0, 'V3' => 231.6],
        'raw_payload' => ['voltages' => ['V1' => 229.2, 'V2' => 230.0, 'V3' => 231.6]],
        'validation_status' => ValidationStatus::Valid,
    ]);
    DeviceTelemetryLog::factory()->forDevice($device)->forTopic($topic)->create([
        'recorded_at' => $baseTime->copy()->addMinutes(10),
        'transformed_values' => ['V1' => 230.7, 'V2' => 231.2, 'V3' => 232.8],
        'raw_payload' => ['voltages' => ['V1' => 230.7, 'V2' => 231.2, 'V3' => 232.8]],
        'validation_status' => ValidationStatus::Valid,
    ]);
    DeviceTelemetryLog::factory()->forDevice($otherDevice)->forTopic($topic)->create([
        'recorded_at' => $baseTime->copy()->addMinutes(10),
        'transformed_values' => ['V1' => 999.0, 'V2' => 999.0, 'V3' => 999.0],
        'raw_payload' => ['voltages' => ['V1' => 999.0, 'V2' => 999.0, 'V3' => 999.0]],
        'validation_status' => ValidationStatus::Valid,
    ]);

    $response = $this->getJson(route('admin.iot-dashboard.widgets.series', ['widget' => $widget]));

    $response->assertOk();
    $payload = $response->json();

    expect($payload)->toHaveKeys(['widget_id', 'topic_id', 'device_id', 'series'])
        ->and($payload['widget_id'])->toBe($widget->id)
        ->and($payload['topic_id'])->toBe($topic->id)
        ->and($payload['device_id'])->toBe($device->id)
        ->and($payload['series'])->toHaveCount(3);

    $seriesByKey = collect($payload['series'])->keyBy('key');

    expect($seriesByKey->keys()->all())->toBe(['V1', 'V2', 'V3'])
        ->and($seriesByKey['V1']['points'])->toHaveCount(3)
        ->and($seriesByKey['V2']['points'])->toHaveCount(3)
        ->and($seriesByKey['V3']['points'])->toHaveCount(3)
        ->and($seriesByKey['V1']['points'][2]['value'])->toBe(230.7)
        ->and($seriesByKey['V2']['points'][2]['value'])->toBe(231.2)
        ->and($seriesByKey['V3']['points'][2]['value'])->toBe(232.8)
        ->and($seriesByKey['V1']['points'][2]['value'])->not->toBe(999.0)
        ->and($seriesByKey['V2']['points'][2]['value'])->not->toBe(999.0)
        ->and($seriesByKey['V3']['points'][2]['value'])->not->toBe(999.0);
});

it('forbids polling endpoint access for users outside the widget organization', function (): void {
    [, , $widget] = createDashboardWidgetContext();

    $user = User::factory()->create(['is_super_admin' => false]);
    $this->actingAs($user);

    $this->getJson(route('admin.iot-dashboard.widgets.series', ['widget' => $widget]))
        ->assertForbidden();
});

it('allows polling endpoint access for organization members', function (): void {
    [$organization, , $widget] = createDashboardWidgetContext();

    $user = User::factory()->create(['is_super_admin' => false]);
    $user->organizations()->attach($organization->id);
    $this->actingAs($user);

    $this->getJson(route('admin.iot-dashboard.widgets.series', ['widget' => $widget]))
        ->assertOk();
});
