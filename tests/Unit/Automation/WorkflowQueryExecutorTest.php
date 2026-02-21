<?php

declare(strict_types=1);

use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\Automation\Services\WorkflowQueryExecutor;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createWorkflowQueryFixture(): array
{
    $organization = Organization::factory()->create();

    $deviceType = DeviceType::factory()->forOrganization($organization->id)->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);

    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    $publishTopic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    $energyParameter = ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $publishTopic->id,
        'key' => 'total_energy',
        'label' => 'Total Energy',
        'json_path' => 'energy.total',
        'type' => ParameterDataType::Decimal,
        'required' => true,
        'is_active' => true,
    ]);

    $workflow = AutomationWorkflow::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $workflowVersion = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
    ]);

    $run = AutomationRun::factory()->create([
        'organization_id' => $organization->id,
        'workflow_id' => $workflow->id,
        'workflow_version_id' => $workflowVersion->id,
    ]);

    return [
        'organization' => $organization,
        'device' => $device,
        'publishTopic' => $publishTopic,
        'energyParameter' => $energyParameter,
        'run' => $run,
    ];
}

it('rejects mutating sql statements for query nodes', function (): void {
    expect(fn () => app(WorkflowQueryExecutor::class)->validateSql('SELECT update AS value FROM source_1'))
        ->toThrow(RuntimeException::class, 'forbidden keyword [update]');
});

it('accepts select statements for query nodes', function (): void {
    $sql = app(WorkflowQueryExecutor::class)->validateSql('SELECT 1 AS value');

    expect($sql)->toBe('SELECT 1 AS value');
});

it('computes a numeric value from source ctes in the configured telemetry window', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Query executor SQL execution test requires PostgreSQL.');
    }

    $fixture = createWorkflowQueryFixture();
    $windowEnd = Carbon::parse('2026-02-20 10:00:00');

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => ['total_energy' => 0.45],
            'raw_payload' => ['energy' => ['total' => 0.45]],
            'recorded_at' => $windowEnd->copy()->subMinutes(6),
            'received_at' => $windowEnd->copy()->subMinutes(6),
        ]);

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => ['total_energy' => 0.67],
            'raw_payload' => ['energy' => ['total' => 0.67]],
            'recorded_at' => $windowEnd->copy()->subMinutes(2),
            'received_at' => $windowEnd->copy()->subMinutes(2),
        ]);

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => ['total_energy' => 5.0],
            'raw_payload' => ['energy' => ['total' => 5.0]],
            'recorded_at' => $windowEnd->copy()->subMinutes(40),
            'received_at' => $windowEnd->copy()->subMinutes(40),
        ]);

    $result = app(WorkflowQueryExecutor::class)->execute(
        run: $fixture['run'],
        config: [
            'mode' => 'sql',
            'window' => [
                'size' => 15,
                'unit' => 'minute',
            ],
            'sources' => [
                [
                    'alias' => 'source_1',
                    'device_id' => $fixture['device']->id,
                    'topic_id' => $fixture['publishTopic']->id,
                    'parameter_definition_id' => $fixture['energyParameter']->id,
                ],
            ],
            'sql' => 'SELECT COALESCE(SUM(source_1.value), 0) AS value FROM source_1',
        ],
        executionContext: [
            'trigger' => [
                'recorded_at' => $windowEnd->toIso8601String(),
            ],
        ],
    );

    expect((float) $result['value'])->toEqualWithDelta(1.12, 0.00001)
        ->and($result['window']['unit'])->toBe('minute')
        ->and($result['window']['size'])->toBe(15)
        ->and($result['sources'])->toHaveCount(1)
        ->and($result['sources'][0]['alias'])->toBe('source_1');
});

it('accepts dollar-prefixed json paths for source parameters', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Query executor SQL execution test requires PostgreSQL.');
    }

    $fixture = createWorkflowQueryFixture();
    $fixture['energyParameter']->update([
        'json_path' => '$.energy.total',
    ]);

    $windowEnd = Carbon::parse('2026-02-20 10:00:00');

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => [],
            'raw_payload' => ['energy' => ['total' => 0.45]],
            'recorded_at' => $windowEnd->copy()->subMinutes(6),
            'received_at' => $windowEnd->copy()->subMinutes(6),
        ]);

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => [],
            'raw_payload' => ['energy' => ['total' => 0.67]],
            'recorded_at' => $windowEnd->copy()->subMinutes(2),
            'received_at' => $windowEnd->copy()->subMinutes(2),
        ]);

    $result = app(WorkflowQueryExecutor::class)->execute(
        run: $fixture['run'],
        config: [
            'mode' => 'sql',
            'window' => [
                'size' => 15,
                'unit' => 'minute',
            ],
            'sources' => [
                [
                    'alias' => 'source_1',
                    'device_id' => $fixture['device']->id,
                    'topic_id' => $fixture['publishTopic']->id,
                    'parameter_definition_id' => $fixture['energyParameter']->id,
                ],
            ],
            'sql' => 'SELECT COALESCE(SUM(source_1.value), 0) AS value FROM source_1',
        ],
        executionContext: [
            'trigger' => [
                'recorded_at' => $windowEnd->toIso8601String(),
            ],
        ],
    );

    expect((float) $result['value'])->toEqualWithDelta(1.12, 0.00001);
});
