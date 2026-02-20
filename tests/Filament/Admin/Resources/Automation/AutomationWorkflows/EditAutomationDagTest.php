<?php

declare(strict_types=1);

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages\EditAutomationDag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createDagOrganization(): Organization
{
    return Organization::query()->create([
        'name' => 'Org '.Str::lower(Str::random(6)),
        'slug' => 'org-'.Str::lower(Str::random(10)),
    ]);
}

function createDagWorkflow(Organization $organization, ?int $activeVersionId = null, ?int $updatedBy = null): AutomationWorkflow
{
    return AutomationWorkflow::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Workflow '.Str::lower(Str::random(6)),
        'slug' => 'workflow-'.Str::lower(Str::random(10)),
        'status' => AutomationWorkflowStatus::Draft->value,
        'active_version_id' => $activeVersionId,
        'created_by' => $updatedBy,
        'updated_by' => $updatedBy,
    ]);
}

function createDagEditorFixture(Organization $organization): array
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

    $blinkParameter = ParameterDefinition::factory()->subscribe()->create([
        'schema_version_topic_id' => $subscribeTopic->id,
        'key' => 'blink',
        'label' => 'Blink',
        'json_path' => 'blink',
        'type' => ParameterDataType::Boolean,
        'required' => true,
        'default_value' => false,
        'is_active' => true,
    ]);

    $colorParameter = ParameterDefinition::factory()->subscribe()->create([
        'schema_version_topic_id' => $subscribeTopic->id,
        'key' => 'color',
        'label' => 'Color',
        'json_path' => 'color',
        'type' => ParameterDataType::String,
        'required' => true,
        'validation_rules' => [
            'enum' => ['RED', 'GREEN', 'BLUE'],
        ],
        'default_value' => 'RED',
        'is_active' => true,
    ]);

    return [
        'organization' => $organization,
        'device' => $device,
        'publishTopic' => $publishTopic,
        'subscribeTopic' => $subscribeTopic,
        'voltageParameter' => $voltageParameter,
        'blinkParameter' => $blinkParameter,
        'colorParameter' => $colorParameter,
    ];
}

function buildVoltageBlinkGraph(array $fixture): array
{
    return [
        'version' => 1,
        'nodes' => [
            [
                'id' => 'trigger-1',
                'type' => 'telemetry-trigger',
                'data' => [
                    'label' => 'Voltage Trigger',
                    'summary' => 'Energy meter voltage source',
                    'config' => [
                        'mode' => 'event',
                        'source' => [
                            'device_id' => $fixture['device']->id,
                            'topic_id' => $fixture['publishTopic']->id,
                            'parameter_definition_id' => $fixture['voltageParameter']->id,
                        ],
                    ],
                ],
                'position' => ['x' => 120, 'y' => 100],
            ],
            [
                'id' => 'condition-1',
                'type' => 'condition',
                'data' => [
                    'label' => 'Voltage > 240',
                    'summary' => 'trigger.value > 240',
                    'config' => [
                        'mode' => 'guided',
                        'guided' => [
                            'left' => 'trigger.value',
                            'operator' => '>',
                            'right' => 240,
                        ],
                        'json_logic' => [
                            '>' => [
                                ['var' => 'trigger.value'],
                                240,
                            ],
                        ],
                    ],
                ],
                'position' => ['x' => 420, 'y' => 100],
            ],
            [
                'id' => 'command-1',
                'type' => 'command',
                'data' => [
                    'label' => 'RGB RED Blink',
                    'summary' => 'Blink red command',
                    'config' => [
                        'target' => [
                            'device_id' => $fixture['device']->id,
                            'topic_id' => $fixture['subscribeTopic']->id,
                        ],
                        'payload_mode' => 'schema_form',
                        'payload' => [
                            'blink' => true,
                            'color' => 'RED',
                        ],
                    ],
                ],
                'position' => ['x' => 760, 'y' => 100],
            ],
        ],
        'edges' => [
            ['id' => 'edge-1', 'source' => 'trigger-1', 'target' => 'condition-1'],
            ['id' => 'edge-2', 'source' => 'condition-1', 'target' => 'command-1'],
        ],
        'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
    ];
}

function buildQueryAlertGraph(array $fixture): array
{
    return [
        'version' => 1,
        'nodes' => [
            [
                'id' => 'trigger-1',
                'type' => 'telemetry-trigger',
                'data' => [
                    'label' => 'Energy Trigger',
                    'summary' => 'Telemetry source for energy',
                    'config' => [
                        'mode' => 'event',
                        'source' => [
                            'device_id' => $fixture['device']->id,
                            'topic_id' => $fixture['publishTopic']->id,
                            'parameter_definition_id' => $fixture['voltageParameter']->id,
                        ],
                    ],
                ],
                'position' => ['x' => 120, 'y' => 100],
            ],
            [
                'id' => 'query-1',
                'type' => 'query',
                'data' => [
                    'label' => 'Windowed Query',
                    'summary' => '1 source(s), 15 minute(s)',
                    'config' => [
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
                                'parameter_definition_id' => $fixture['voltageParameter']->id,
                            ],
                        ],
                        'sql' => 'SELECT AVG(source_1.value) AS value FROM source_1',
                    ],
                ],
                'position' => ['x' => 420, 'y' => 100],
            ],
            [
                'id' => 'condition-1',
                'type' => 'condition',
                'data' => [
                    'label' => 'Query > 1',
                    'summary' => 'query.value > 1',
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
                'position' => ['x' => 700, 'y' => 100],
            ],
            [
                'id' => 'alert-1',
                'type' => 'alert',
                'data' => [
                    'label' => 'Email Alert',
                    'summary' => '2 recipient(s), cooldown 30 minute(s)',
                    'config' => [
                        'channel' => 'email',
                        'recipients' => ['ops@example.com', 'alerts@example.com'],
                        'subject' => 'Energy threshold exceeded: {{ query.value }}',
                        'body' => 'Run {{ run.id }}',
                        'cooldown' => [
                            'value' => 30,
                            'unit' => 'minute',
                        ],
                    ],
                ],
                'position' => ['x' => 980, 'y' => 100],
            ],
        ],
        'edges' => [
            ['id' => 'edge-1', 'source' => 'trigger-1', 'target' => 'query-1'],
            ['id' => 'edge-2', 'source' => 'query-1', 'target' => 'condition-1'],
            ['id' => 'edge-3', 'source' => 'condition-1', 'target' => 'alert-1'],
        ],
        'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
    ];
}

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('can render the dag editor page', function (): void {
    $workflow = createDagWorkflow(createDagOrganization(), updatedBy: $this->admin->id);
    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
        'version' => 1,
    ]);

    $workflow->update(['active_version_id' => $version->id]);

    livewire(EditAutomationDag::class, ['record' => $workflow->id])
        ->assertSuccessful()
        ->assertSee('DAG Editor');
});

it('returns organization scoped telemetry and command options', function (): void {
    $organization = createDagOrganization();
    $workflow = createDagWorkflow($organization, updatedBy: $this->admin->id);

    $fixture = createDagEditorFixture($organization);

    $otherFixture = createDagEditorFixture(createDagOrganization());

    $component = livewire(EditAutomationDag::class, ['record' => $workflow->id]);

    $component
        ->call('getTelemetryTriggerOptions', [
            'device_id' => $fixture['device']->id,
            'topic_id' => $fixture['publishTopic']->id,
        ])
        ->assertReturned(function (mixed $response) use ($fixture, $otherFixture): bool {
            if (! is_array($response)) {
                return false;
            }

            $deviceIds = collect($response['devices'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $topicIds = collect($response['topics'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $parameterIds = collect($response['parameters'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            return in_array($fixture['device']->id, $deviceIds, true)
                && ! in_array($otherFixture['device']->id, $deviceIds, true)
                && in_array($fixture['publishTopic']->id, $topicIds, true)
                && in_array($fixture['voltageParameter']->id, $parameterIds, true);
        })
        ->call('getQueryNodeOptions', [
            'device_id' => $fixture['device']->id,
            'topic_id' => $fixture['publishTopic']->id,
        ])
        ->assertReturned(function (mixed $response) use ($fixture, $otherFixture): bool {
            if (! is_array($response)) {
                return false;
            }

            $deviceIds = collect($response['devices'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $topicIds = collect($response['topics'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $parameterIds = collect($response['parameters'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            return in_array($fixture['device']->id, $deviceIds, true)
                && ! in_array($otherFixture['device']->id, $deviceIds, true)
                && in_array($fixture['publishTopic']->id, $topicIds, true)
                && in_array($fixture['voltageParameter']->id, $parameterIds, true);
        })
        ->call('getCommandNodeOptions', [
            'device_id' => $fixture['device']->id,
            'topic_id' => $fixture['subscribeTopic']->id,
        ])
        ->assertReturned(function (mixed $response) use ($fixture, $otherFixture): bool {
            if (! is_array($response)) {
                return false;
            }

            $deviceIds = collect($response['devices'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $topicIds = collect($response['topics'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $parameterIds = collect($response['parameters'] ?? [])->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            return in_array($fixture['device']->id, $deviceIds, true)
                && ! in_array($otherFixture['device']->id, $deviceIds, true)
                && in_array($fixture['subscribeTopic']->id, $topicIds, true)
                && in_array($fixture['blinkParameter']->id, $parameterIds, true)
                && in_array($fixture['colorParameter']->id, $parameterIds, true);
        });
});

it('returns latest telemetry preview for selected source parameter', function (): void {
    $organization = createDagOrganization();
    $workflow = createDagWorkflow($organization, updatedBy: $this->admin->id);

    $fixture = createDagEditorFixture($organization);

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => ['metrics' => ['voltage' => 220.4]],
            'raw_payload' => ['metrics' => ['voltage' => 220.4]],
            'recorded_at' => now()->subMinutes(10),
            'received_at' => now()->subMinutes(10),
        ]);

    DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['publishTopic'])
        ->create([
            'transformed_values' => ['metrics' => ['voltage' => 245.9]],
            'raw_payload' => ['metrics' => ['voltage' => 245.9]],
            'recorded_at' => now()->subMinute(),
            'received_at' => now()->subMinute(),
        ]);

    livewire(EditAutomationDag::class, ['record' => $workflow->id])
        ->call('previewLatestTelemetryValue', [
            'device_id' => $fixture['device']->id,
            'topic_id' => $fixture['publishTopic']->id,
            'parameter_definition_id' => $fixture['voltageParameter']->id,
        ])
        ->assertReturned(function (mixed $response): bool {
            return is_array($response)
                && ($response['value'] ?? null) === 245.9
                && (($response['parameter']['key'] ?? null) === 'voltage')
                && is_string($response['recorded_at'] ?? null);
        });
});

it('saves configured dag graph and compiles telemetry trigger rows', function (): void {
    $organization = createDagOrganization();
    $workflow = createDagWorkflow($organization, updatedBy: $this->admin->id);

    $fixture = createDagEditorFixture($organization);

    $graph = buildVoltageBlinkGraph($fixture);

    livewire(EditAutomationDag::class, ['record' => $workflow->id])
        ->call('saveGraph', $graph)
        ->assertNotified('DAG saved');

    $workflow->refresh();
    $version = $workflow->activeVersion()->first();

    expect($version)->not->toBeNull()
        ->and($version?->graph_json)->toMatchArray($graph)
        ->and($workflow->updated_by)->toBe($this->admin->id);

    $trigger = AutomationTelemetryTrigger::query()
        ->where('workflow_version_id', $version?->id)
        ->first();

    expect($trigger)->not->toBeNull()
        ->and($trigger?->organization_id)->toBe($organization->id)
        ->and($trigger?->device_id)->toBe($fixture['device']->id)
        ->and($trigger?->schema_version_topic_id)->toBe($fixture['publishTopic']->id);
});

it('saves dag graph containing query and alert nodes', function (): void {
    $organization = createDagOrganization();
    $workflow = createDagWorkflow($organization, updatedBy: $this->admin->id);
    $fixture = createDagEditorFixture($organization);
    $graph = buildQueryAlertGraph($fixture);

    livewire(EditAutomationDag::class, ['record' => $workflow->id])
        ->call('saveGraph', $graph)
        ->assertNotified('DAG saved');

    $workflow->refresh();
    $version = $workflow->activeVersion()->first();

    expect($version)->not->toBeNull()
        ->and($version?->graph_json)->toMatchArray($graph);

    $trigger = AutomationTelemetryTrigger::query()
        ->where('workflow_version_id', $version?->id)
        ->first();

    expect($trigger)->not->toBeNull()
        ->and($trigger?->device_id)->toBe($fixture['device']->id)
        ->and($trigger?->schema_version_topic_id)->toBe($fixture['publishTopic']->id);
});

it('does not save invalid cyclic graphs', function (): void {
    $workflow = createDagWorkflow(createDagOrganization(), updatedBy: $this->admin->id);

    $cyclicGraph = [
        'version' => 1,
        'nodes' => [
            ['id' => 'trigger', 'type' => 'telemetry-trigger'],
            ['id' => 'node-a', 'type' => 'condition'],
            ['id' => 'node-b', 'type' => 'alert'],
        ],
        'edges' => [
            ['source' => 'trigger', 'target' => 'node-a'],
            ['source' => 'node-a', 'target' => 'node-b'],
            ['source' => 'node-b', 'target' => 'node-a'],
        ],
    ];

    livewire(EditAutomationDag::class, ['record' => $workflow->id])
        ->call('saveGraph', $cyclicGraph)
        ->assertNotified('Graph validation failed.');

    expect($workflow->versions()->count())->toBe(0)
        ->and($workflow->fresh()->active_version_id)->toBeNull();
});
