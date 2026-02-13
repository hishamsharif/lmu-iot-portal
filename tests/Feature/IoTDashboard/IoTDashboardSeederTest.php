<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use Database\Seeders\DeviceSchemaSeeder;
use Database\Seeders\IoTDashboardSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds an energy meter dashboard widget with V1, V2, and V3 series', function (): void {
    $this->seed([
        OrganizationSeeder::class,
        DeviceSchemaSeeder::class,
    ]);

    $organization = Organization::query()->orderBy('id')->first();
    $deviceType = DeviceType::query()->where('key', 'energy_meter')->first();
    $schemaVersion = DeviceSchemaVersion::query()
        ->whereHas('schema.deviceType', fn ($query) => $query->where('key', 'energy_meter'))
        ->where('status', 'active')
        ->orderBy('id')
        ->first();

    expect($organization)->not->toBeNull()
        ->and($deviceType)->not->toBeNull()
        ->and($schemaVersion)->not->toBeNull();

    Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'name' => 'Seeder Test Energy Meter',
    ]);

    $this->seed(IoTDashboardSeeder::class);

    $dashboard = IoTDashboard::query()->where('slug', 'energy-meter-dashboard')->first();
    $widget = IoTDashboardWidget::query()
        ->with(['topic', 'device'])
        ->where('title', 'Energy Meter Voltages (V1 / V2 / V3)')
        ->first();

    expect($dashboard)->not->toBeNull()
        ->and($widget)->not->toBeNull()
        ->and($widget?->type)->toBe('line_chart')
        ->and($widget?->topic?->key)->toBe('telemetry')
        ->and($widget?->device)->not->toBeNull();

    $seriesKeys = collect($widget?->series_config ?? [])
        ->pluck('key')
        ->all();

    expect($seriesKeys)->toBe(['V1', 'V2', 'V3']);
});
