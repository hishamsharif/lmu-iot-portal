<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterCategory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportRunJob;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('reporting.api.token', 'internal-reporting-test-token');
    config()->set('reporting.api.token_header', 'X-Reporting-Token');
    config()->set('reporting.storage_disk', 'local');
});

/**
 * @return array{
 *     organization: Organization,
 *     user: User,
 *     device: Device,
 *     topic: SchemaVersionTopic,
 * }
 */
function createReportingDeviceContext(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach($organization->id);

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

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'total_energy_kwh',
        'label' => 'Total Energy',
        'type' => ParameterDataType::Decimal,
        'category' => ParameterCategory::Counter,
        'is_active' => true,
        'sequence' => 1,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'machine_state',
        'label' => 'Machine State',
        'type' => ParameterDataType::String,
        'category' => ParameterCategory::State,
        'is_active' => true,
        'sequence' => 2,
        'validation_rules' => ['category' => 'enum'],
    ]);

    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    return compact('organization', 'user', 'device', 'topic');
}

it('queues a report run through the internal reporting API', function (): void {
    Queue::fake();

    ['organization' => $organization, 'user' => $user, 'device' => $device] = createReportingDeviceContext();

    $response = $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->postJson('/api/internal/reporting/report-runs', [
            'organization_id' => $organization->id,
            'requested_by_user_id' => $user->id,
            'device_id' => $device->id,
            'type' => ReportType::CounterConsumption->value,
            'grouping' => 'hourly',
            'parameter_keys' => ['total_energy_kwh'],
            'from_at' => now()->subDay()->toIso8601String(),
            'until_at' => now()->toIso8601String(),
            'timezone' => 'UTC',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.device_id', $device->id)
        ->assertJsonPath('data.type', ReportType::CounterConsumption->value);

    $this->assertDatabaseHas('report_runs', [
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'type' => ReportType::CounterConsumption->value,
        'status' => ReportRunStatus::Queued->value,
    ]);

    Queue::assertPushed(GenerateReportRunJob::class);
});

it('rejects internal reporting API requests without the configured token', function (): void {
    ['organization' => $organization, 'user' => $user, 'device' => $device] = createReportingDeviceContext();

    $this->postJson('/api/internal/reporting/report-runs', [
        'organization_id' => $organization->id,
        'requested_by_user_id' => $user->id,
        'device_id' => $device->id,
        'type' => ReportType::ParameterValues->value,
        'from_at' => now()->subHour()->toIso8601String(),
        'until_at' => now()->toIso8601String(),
        'timezone' => 'UTC',
    ])->assertUnauthorized();
});

it('requires aggregation window for counter and state reports', function (): void {
    ['organization' => $organization, 'user' => $user, 'device' => $device] = createReportingDeviceContext();

    $response = $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->postJson('/api/internal/reporting/report-runs', [
            'organization_id' => $organization->id,
            'requested_by_user_id' => $user->id,
            'device_id' => $device->id,
            'type' => ReportType::CounterConsumption->value,
            'from_at' => now()->subDay()->toIso8601String(),
            'until_at' => now()->toIso8601String(),
            'timezone' => 'UTC',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['grouping']);
});

it('rejects shift schedule grouping when selected schedule is not a continuous daily cycle', function (): void {
    ['organization' => $organization, 'user' => $user, 'device' => $device] = createReportingDeviceContext();

    $response = $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->postJson('/api/internal/reporting/report-runs', [
            'organization_id' => $organization->id,
            'requested_by_user_id' => $user->id,
            'device_id' => $device->id,
            'type' => ReportType::CounterConsumption->value,
            'grouping' => 'shift_schedule',
            'from_at' => now()->subDay()->toIso8601String(),
            'until_at' => now()->toIso8601String(),
            'timezone' => 'UTC',
            'payload' => [
                'shift_schedule' => [
                    'id' => 'invalid',
                    'name' => 'Invalid Cycle',
                    'windows' => [
                        ['id' => 'a', 'name' => 'A', 'start' => '06:00', 'end' => '14:00'],
                        ['id' => 'b', 'name' => 'B', 'start' => '13:00', 'end' => '22:00'],
                        ['id' => 'c', 'name' => 'C', 'start' => '22:00', 'end' => '06:00'],
                    ],
                ],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['grouping']);
});

it('updates organization report settings through the internal reporting API', function (): void {
    ['organization' => $organization] = createReportingDeviceContext();

    $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->putJson('/api/internal/reporting/organization-report-settings', [
            'organization_id' => $organization->id,
            'timezone' => 'Asia/Colombo',
            'max_range_days' => 21,
            'shift_schedules' => [
                [
                    'id' => 'two-shift',
                    'name' => 'Two Shift',
                    'windows' => [
                        ['id' => 'day', 'name' => 'Day', 'start' => '08:00', 'end' => '20:00'],
                        ['id' => 'night', 'name' => 'Night', 'start' => '20:00', 'end' => '08:00'],
                    ],
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.timezone', 'Asia/Colombo')
        ->assertJsonPath('data.max_range_days', 21)
        ->assertJsonPath('data.shift_schedules.0.name', 'Two Shift');

    $this->assertDatabaseHas('organization_report_settings', [
        'organization_id' => $organization->id,
        'timezone' => 'Asia/Colombo',
        'max_range_days' => 21,
    ]);
});

it('accepts shift schedules with non-overlapping gaps', function (): void {
    ['organization' => $organization] = createReportingDeviceContext();

    $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->putJson('/api/internal/reporting/organization-report-settings', [
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'max_range_days' => 21,
            'shift_schedules' => [
                [
                    'id' => 'uneven',
                    'name' => 'Uneven',
                    'windows' => [
                        ['id' => 'a', 'name' => 'A', 'start' => '07:30', 'end' => '15:30'],
                        ['id' => 'b', 'name' => 'B', 'start' => '15:30', 'end' => '20:00'],
                        ['id' => 'c', 'name' => 'C', 'start' => '20:30', 'end' => '07:30'],
                    ],
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.shift_schedules.0.name', 'Uneven');
});

it('rejects organization shift schedules with overlaps', function (): void {
    ['organization' => $organization] = createReportingDeviceContext();

    $response = $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->putJson('/api/internal/reporting/organization-report-settings', [
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'max_range_days' => 21,
            'shift_schedules' => [
                [
                    'name' => 'Shift A',
                    'windows' => [
                        ['name' => 'A', 'start' => '06:00', 'end' => '14:00'],
                        ['name' => 'B', 'start' => '13:00', 'end' => '22:00'],
                        ['name' => 'C', 'start' => '22:00', 'end' => '06:00'],
                    ],
                ],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['shift_schedules.0.windows']);
});

it('downloads a completed report through the internal reporting API', function (): void {
    Storage::fake('local');

    ['organization' => $organization, 'user' => $user, 'device' => $device, 'topic' => $topic] = createReportingDeviceContext();

    DeviceTelemetryLog::factory()
        ->forDevice($device)
        ->forTopic($topic)
        ->create([
            'recorded_at' => Carbon::parse('2026-02-17 08:00:00'),
            'raw_payload' => ['energy' => ['total_energy_kwh' => 120.2]],
            'transformed_values' => ['total_energy_kwh' => 120.2, 'machine_state' => 'idle'],
            'validation_status' => ValidationStatus::Valid,
        ]);

    Storage::disk('local')->put('reports/test-report.csv', "a,b\n1,2\n");

    $reportRun = ReportRun::query()->create([
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $user->id,
        'type' => ReportType::ParameterValues,
        'status' => ReportRunStatus::Completed,
        'format' => 'csv',
        'from_at' => now()->subDay(),
        'until_at' => now(),
        'timezone' => 'UTC',
        'parameter_keys' => ['total_energy_kwh'],
        'storage_disk' => 'local',
        'storage_path' => 'reports/test-report.csv',
        'file_name' => 'test-report.csv',
        'file_size' => 8,
        'generated_at' => now(),
    ]);

    $response = $this->withHeaders(['X-Reporting-Token' => 'internal-reporting-test-token'])
        ->get("/api/internal/reporting/report-runs/{$reportRun->id}/download?organization_id={$organization->id}");

    $response->assertSuccessful();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('test-report.csv');
});
