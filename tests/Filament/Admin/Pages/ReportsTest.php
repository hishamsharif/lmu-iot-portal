<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\ParameterCategory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Reporting\Enums\ReportGrouping;
use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Models\OrganizationReportSetting;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Pages\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the reports page and lists report history rows', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $device = Device::factory()->create(['organization_id' => $organization->id]);

    $reportRun = ReportRun::query()->create([
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $admin->id,
        'type' => ReportType::ParameterValues,
        'status' => ReportRunStatus::Queued,
        'format' => 'csv',
        'from_at' => now()->subDay(),
        'until_at' => now(),
        'timezone' => 'UTC',
    ]);

    $this->actingAs($admin);

    livewire(Reports::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$reportRun]));
});

it('normalizes enum-backed report inputs from the form payload', function (): void {
    $component = app(Reports::class);

    $normalizeType = new ReflectionMethod($component, 'normalizeReportTypeValue');
    $normalizeType->setAccessible(true);

    $normalizeGrouping = new ReflectionMethod($component, 'normalizeReportGroupingValue');
    $normalizeGrouping->setAccessible(true);

    expect($normalizeType->invoke($component, ReportType::CounterConsumption))
        ->toBe(ReportType::CounterConsumption->value)
        ->and($normalizeType->invoke($component, null))
        ->toBe(ReportType::ParameterValues->value)
        ->and($normalizeGrouping->invoke($component, ReportGrouping::Daily))
        ->toBe(ReportGrouping::Daily->value)
        ->and($normalizeGrouping->invoke($component, null))
        ->toBe(ReportGrouping::Hourly->value);
});

it('returns only counter parameters when report type is counter consumption enum', function (): void {
    $organization = Organization::factory()->create();
    $device = Device::factory()->create(['organization_id' => $organization->id]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $device->device_schema_version_id,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'energy_total_kwh',
        'label' => 'Energy Total',
        'type' => ParameterDataType::Decimal,
        'category' => ParameterCategory::Counter,
        'is_active' => true,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'temperature_c',
        'label' => 'Temperature',
        'type' => ParameterDataType::Decimal,
        'category' => ParameterCategory::Measurement,
        'is_active' => true,
    ]);

    $component = app(Reports::class);
    $method = new ReflectionMethod($component, 'parameterOptionsForSelection');
    $method->setAccessible(true);

    /** @var array<string, string> $options */
    $options = $method->invoke($component, $device->id, ReportType::CounterConsumption);

    expect($options)
        ->toHaveKey('energy_total_kwh')
        ->not->toHaveKey('temperature_c');
});

it('returns report type options based on available parameter categories for selected device', function (): void {
    $organization = Organization::factory()->create();
    $device = Device::factory()->create(['organization_id' => $organization->id]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $device->device_schema_version_id,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'energy_total_kwh',
        'label' => 'Energy Total',
        'type' => ParameterDataType::Decimal,
        'category' => ParameterCategory::Counter,
        'is_active' => true,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'machine_state',
        'label' => 'Machine State',
        'type' => ParameterDataType::String,
        'category' => ParameterCategory::State,
        'is_active' => true,
    ]);

    $component = app(Reports::class);
    $method = new ReflectionMethod($component, 'reportTypeOptionsForDevice');
    $method->setAccessible(true);

    /** @var array<string, string> $options */
    $options = $method->invoke($component, $device->id);

    expect($options)
        ->toHaveKey(ReportType::ParameterValues->value)
        ->toHaveKey(ReportType::CounterConsumption->value)
        ->toHaveKey(ReportType::StateUtilization->value);
});

it('adds custom shift schedule aggregation options only when schedules exist', function (): void {
    $organization = Organization::factory()->create();
    $component = app(Reports::class);

    $method = new ReflectionMethod($component, 'aggregationWindowOptionsForSelection');
    $method->setAccessible(true);

    /** @var array<string, string> $withoutSchedules */
    $withoutSchedules = $method->invoke($component, $organization->id, ReportType::CounterConsumption);

    expect($withoutSchedules)->toHaveKey(ReportGrouping::Hourly->value)
        ->toHaveKey(ReportGrouping::Daily->value)
        ->toHaveKey(ReportGrouping::Monthly->value)
        ->not->toHaveKey(ReportGrouping::ShiftSchedule->value);

    OrganizationReportSetting::factory()->create([
        'organization_id' => $organization->id,
        'shift_schedules' => [
            [
                'id' => 'three-shift',
                'name' => 'Three Shift',
                'windows' => [
                    ['id' => 'a', 'name' => 'A', 'start' => '06:00', 'end' => '14:00'],
                    ['id' => 'b', 'name' => 'B', 'start' => '14:00', 'end' => '22:00'],
                    ['id' => 'c', 'name' => 'C', 'start' => '22:00', 'end' => '06:00'],
                ],
            ],
            [
                'id' => 'uneven',
                'name' => 'Uneven',
                'windows' => [
                    ['id' => 'x', 'name' => 'X', 'start' => '07:30', 'end' => '15:30'],
                    ['id' => 'y', 'name' => 'Y', 'start' => '15:30', 'end' => '20:30'],
                    ['id' => 'z', 'name' => 'Z', 'start' => '20:30', 'end' => '07:30'],
                ],
            ],
        ],
    ]);

    /** @var array<string, string> $withSchedules */
    $withSchedules = $method->invoke($component, $organization->id, ReportType::CounterConsumption);

    expect($withSchedules)
        ->toHaveKey('shift_schedule:three-shift')
        ->toHaveKey('shift_schedule:uneven')
        ->and($withSchedules['shift_schedule:three-shift'])
        ->toBe('Shift: Three Shift');
});
