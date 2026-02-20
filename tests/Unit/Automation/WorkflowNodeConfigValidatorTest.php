<?php

declare(strict_types=1);

use App\Domain\Automation\Data\WorkflowGraph;
use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Services\WorkflowNodeConfigValidator;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createOrganizationRecord(): Organization
{
    return Organization::query()->create([
        'name' => 'Org '.Str::lower(Str::random(6)),
        'slug' => 'org-'.Str::lower(Str::random(10)),
    ]);
}

function createWorkflowRecord(Organization $organization): AutomationWorkflow
{
    return AutomationWorkflow::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Workflow '.Str::lower(Str::random(6)),
        'slug' => 'workflow-'.Str::lower(Str::random(10)),
        'status' => AutomationWorkflowStatus::Draft->value,
        'created_by' => null,
        'updated_by' => null,
    ]);
}

function createWorkflowDeviceFixture(Organization $organization): array
{
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

    $subscribeTopic = SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'commands',
        'suffix' => 'commands',
    ]);

    $voltageParameter = ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $publishTopic->id,
        'key' => 'voltage',
        'label' => 'Voltage',
        'json_path' => 'metrics.voltage',
        'type' => ParameterDataType::Decimal,
        'required' => true,
        'is_active' => true,
    ]);

    $brightnessParameter = ParameterDefinition::factory()->subscribe()->create([
        'schema_version_topic_id' => $subscribeTopic->id,
        'key' => 'brightness',
        'label' => 'Brightness',
        'json_path' => 'brightness',
        'type' => ParameterDataType::Integer,
        'required' => true,
        'validation_rules' => [
            'min' => 0,
            'max' => 100,
        ],
        'default_value' => 0,
        'is_active' => true,
    ]);

    return [
        'device' => $device,
        'publishTopic' => $publishTopic,
        'subscribeTopic' => $subscribeTopic,
        'voltageParameter' => $voltageParameter,
        'brightnessParameter' => $brightnessParameter,
    ];
}

it('fails when telemetry trigger config is missing required fields', function (): void {
    $workflow = createWorkflowRecord(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'trigger-1',
                'type' => 'telemetry-trigger',
                'data' => [
                    'config' => [
                        'mode' => 'event',
                        'source' => [
                            'device_id' => 123,
                            'topic_id' => 456,
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'missing source device, topic, or parameter');
});

it('fails when condition node has invalid json logic', function (): void {
    $workflow = createWorkflowRecord(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'condition-1',
                'type' => 'condition',
                'data' => [
                    'config' => [
                        'mode' => 'json_logic',
                        'json_logic' => [],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'must define valid JSON logic');
});

it('fails when command payload values do not match parameter requirements', function (): void {
    $organization = createOrganizationRecord();
    $workflow = createWorkflowRecord($organization);

    $fixture = createWorkflowDeviceFixture($organization);

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'command-1',
                'type' => 'command',
                'data' => [
                    'config' => [
                        'target' => [
                            'device_id' => $fixture['device']->id,
                            'topic_id' => $fixture['subscribeTopic']->id,
                        ],
                        'payload_mode' => 'schema_form',
                        'payload' => [
                            'brightness' => 200,
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'invalid payload values: brightness');
});

it('validates query node configuration when source and sql are valid', function (): void {
    $organization = createOrganizationRecord();
    $workflow = createWorkflowRecord($organization);
    $fixture = createWorkflowDeviceFixture($organization);

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'query-1',
                'type' => 'query',
                'data' => [
                    'config' => [
                        'mode' => 'sql',
                        'window' => [
                            'size' => 30,
                            'unit' => 'minute',
                        ],
                        'sources' => [
                            [
                                'alias' => 'source_1',
                                'device_id' => $fixture['device']->id,
                                'topic_id' => $fixture['publishTopic']->id,
                                'parameter_definition_id' => $fixture['voltageParameter']->id,
                            ],
                        ],
                        'sql' => 'SELECT AVG(source_1.value) AS value FROM source_1',
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->not->toThrow(RuntimeException::class);
});

it('fails when query node sql is not select-only', function (): void {
    $organization = createOrganizationRecord();
    $workflow = createWorkflowRecord($organization);
    $fixture = createWorkflowDeviceFixture($organization);

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'query-1',
                'type' => 'query',
                'data' => [
                    'config' => [
                        'mode' => 'sql',
                        'window' => [
                            'size' => 30,
                            'unit' => 'minute',
                        ],
                        'sources' => [
                            [
                                'alias' => 'source_1',
                                'device_id' => $fixture['device']->id,
                                'topic_id' => $fixture['publishTopic']->id,
                                'parameter_definition_id' => $fixture['voltageParameter']->id,
                            ],
                        ],
                        'sql' => 'SELECT update AS value FROM source_1',
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'contains forbidden keyword [update]');
});

it('fails when query node source is outside workflow organization scope', function (): void {
    $organization = createOrganizationRecord();
    $workflow = createWorkflowRecord($organization);
    $otherFixture = createWorkflowDeviceFixture(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'query-1',
                'type' => 'query',
                'data' => [
                    'config' => [
                        'mode' => 'sql',
                        'window' => [
                            'size' => 15,
                            'unit' => 'minute',
                        ],
                        'sources' => [
                            [
                                'alias' => 'source_1',
                                'device_id' => $otherFixture['device']->id,
                                'topic_id' => $otherFixture['publishTopic']->id,
                                'parameter_definition_id' => $otherFixture['voltageParameter']->id,
                            ],
                        ],
                        'sql' => 'SELECT AVG(source_1.value) AS value FROM source_1',
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'references invalid device');
});

it('allows guided condition to use query value as left operand', function (): void {
    $workflow = createWorkflowRecord(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'condition-1',
                'type' => 'condition',
                'data' => [
                    'config' => [
                        'mode' => 'guided',
                        'guided' => [
                            'left' => 'query.value',
                            'operator' => '>',
                            'right' => 1,
                        ],
                        'json_logic' => [
                            '>' => [
                                ['var' => 'query.value'],
                                1,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->not->toThrow(RuntimeException::class);
});

it('validates alert node configuration for email channel', function (): void {
    $workflow = createWorkflowRecord(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'alert-1',
                'type' => 'alert',
                'data' => [
                    'config' => [
                        'channel' => 'email',
                        'recipients' => ['alerts@example.com', 'ops@example.com'],
                        'subject' => 'Threshold exceeded',
                        'body' => 'Query value is {{ query.value }}',
                        'cooldown' => [
                            'value' => 30,
                            'unit' => 'minute',
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->not->toThrow(RuntimeException::class);
});

it('fails when alert recipients are invalid email addresses', function (): void {
    $workflow = createWorkflowRecord(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'alert-1',
                'type' => 'alert',
                'data' => [
                    'config' => [
                        'channel' => 'email',
                        'recipients' => ['invalid-email'],
                        'subject' => 'Threshold exceeded',
                        'body' => 'Alert body',
                        'cooldown' => [
                            'value' => 30,
                            'unit' => 'minute',
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'must be valid email addresses');
});

it('fails when alert cooldown configuration is invalid', function (): void {
    $workflow = createWorkflowRecord(createOrganizationRecord());

    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            [
                'id' => 'alert-1',
                'type' => 'alert',
                'data' => [
                    'config' => [
                        'channel' => 'email',
                        'recipients' => ['alerts@example.com'],
                        'subject' => 'Threshold exceeded',
                        'body' => 'Alert body',
                        'cooldown' => [
                            'value' => 0,
                            'unit' => 'minute',
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [],
    ]);

    expect(fn () => app(WorkflowNodeConfigValidator::class)->validate($workflow, $graph))
        ->toThrow(RuntimeException::class, 'cooldown must include positive value');
});
