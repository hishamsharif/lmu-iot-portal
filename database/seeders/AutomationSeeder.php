<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Automation\Data\WorkflowGraph;
use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\Automation\Services\WorkflowGraphValidator;
use App\Domain\Automation\Services\WorkflowNodeConfigValidator;
use App\Domain\Automation\Services\WorkflowTelemetryTriggerCompiler;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Seeder;

class AutomationSeeder extends Seeder
{
    private const WORKFLOW_SLUG = 'energy-meter-power-l1-color-automation';

    private const ENERGY_METER_EXTERNAL_ID = 'main-energy-meter-01';

    private const RGB_CONTROLLER_EXTERNAL_ID = 'rgb-led-01';

    public function __construct(
        protected WorkflowGraphValidator $workflowGraphValidator,
        protected WorkflowNodeConfigValidator $workflowNodeConfigValidator,
        protected WorkflowTelemetryTriggerCompiler $workflowTelemetryTriggerCompiler,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::query()->orderBy('id')->first();

        if (! $organization instanceof Organization) {
            $this->command?->warn('No organization found. Skipping automation seed.');

            return;
        }

        $sourceDevice = Device::query()
            ->where('organization_id', $organization->id)
            ->where('external_id', self::ENERGY_METER_EXTERNAL_ID)
            ->first();

        $targetDevice = Device::query()
            ->where('organization_id', $organization->id)
            ->where('external_id', self::RGB_CONTROLLER_EXTERNAL_ID)
            ->first();

        $sourceSchemaVersionId = $sourceDevice instanceof Device
            ? $this->resolvePositiveInt($sourceDevice->getAttribute('device_schema_version_id'))
            : null;

        $targetSchemaVersionId = $targetDevice instanceof Device
            ? $this->resolvePositiveInt($targetDevice->getAttribute('device_schema_version_id'))
            : null;

        if (! $sourceDevice instanceof Device || ! $targetDevice instanceof Device || $sourceSchemaVersionId === null || $targetSchemaVersionId === null) {
            $this->command?->warn('Required source/target devices were not found. Run device seeders first.');

            return;
        }

        $sourceTopic = SchemaVersionTopic::query()
            ->where('device_schema_version_id', $sourceSchemaVersionId)
            ->where('direction', TopicDirection::Publish->value)
            ->where('key', 'telemetry')
            ->first();

        $sourceParameter = $sourceTopic instanceof SchemaVersionTopic
            ? ParameterDefinition::query()
                ->where('schema_version_topic_id', $sourceTopic->id)
                ->where('key', 'power_l1')
                ->where('is_active', true)
                ->first()
            : null;

        $targetTopic = SchemaVersionTopic::query()
            ->where('device_schema_version_id', $targetSchemaVersionId)
            ->where('direction', TopicDirection::Subscribe->value)
            ->where('key', 'lighting_control')
            ->first();

        if (! $sourceTopic instanceof SchemaVersionTopic || ! $sourceParameter instanceof ParameterDefinition || ! $targetTopic instanceof SchemaVersionTopic) {
            $this->command?->warn('Required source/target topic configuration was not found. Run schema seeders first.');

            return;
        }

        $workflow = AutomationWorkflow::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => self::WORKFLOW_SLUG,
            ],
            [
                'name' => 'Energy Meter Power L1 to RGB Color',
                'status' => AutomationWorkflowStatus::Active->value,
            ],
        );

        $graph = $this->buildGraph(
            sourceDeviceId: $sourceDevice->id,
            sourceTopicId: $sourceTopic->id,
            sourceParameterId: $sourceParameter->id,
            targetDeviceId: $targetDevice->id,
            targetTopicId: $targetTopic->id,
        );

        $workflowGraph = WorkflowGraph::fromArray($graph);
        $this->workflowGraphValidator->validate($workflowGraph);
        $this->workflowNodeConfigValidator->validate($workflow, $workflowGraph);

        $workflowVersion = AutomationWorkflowVersion::query()->updateOrCreate(
            [
                'automation_workflow_id' => $workflow->id,
                'version' => 1,
            ],
            [
                'graph_json' => $graph,
                'graph_checksum' => hash('sha256', json_encode($graph, JSON_THROW_ON_ERROR)),
                'published_at' => now(),
            ],
        );

        $workflow->update([
            'status' => AutomationWorkflowStatus::Active->value,
            'active_version_id' => $workflowVersion->id,
        ]);

        $this->workflowTelemetryTriggerCompiler->compile(
            workflow: $workflow,
            workflowVersion: $workflowVersion,
            graph: $workflowGraph,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGraph(
        int $sourceDeviceId,
        int $sourceTopicId,
        int $sourceParameterId,
        int $targetDeviceId,
        int $targetTopicId,
    ): array {
        return [
            'version' => 1,
            'nodes' => [
                [
                    'id' => 'trigger-power-l1',
                    'type' => 'telemetry-trigger',
                    'data' => [
                        'config' => [
                            'mode' => 'event',
                            'source' => [
                                'device_id' => $sourceDeviceId,
                                'topic_id' => $sourceTopicId,
                                'parameter_definition_id' => $sourceParameterId,
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'condition-yellow',
                    'type' => 'condition',
                    'data' => [
                        'config' => [
                            'mode' => 'json_logic',
                            'json_logic' => [
                                'and' => [
                                    [
                                        '>' => [
                                            ['var' => 'trigger.value'],
                                            20,
                                        ],
                                    ],
                                    [
                                        '<' => [
                                            ['var' => 'trigger.value'],
                                            40,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'condition-purple',
                    'type' => 'condition',
                    'data' => [
                        'config' => [
                            'mode' => 'json_logic',
                            'json_logic' => [
                                'and' => [
                                    [
                                        '>=' => [
                                            ['var' => 'trigger.value'],
                                            40,
                                        ],
                                    ],
                                    [
                                        '<' => [
                                            ['var' => 'trigger.value'],
                                            60,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'condition-blue',
                    'type' => 'condition',
                    'data' => [
                        'config' => [
                            'mode' => 'json_logic',
                            'json_logic' => [
                                '>' => [
                                    ['var' => 'trigger.value'],
                                    60,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'command-yellow',
                    'type' => 'command',
                    'data' => [
                        'config' => [
                            'target' => [
                                'device_id' => $targetDeviceId,
                                'topic_id' => $targetTopicId,
                            ],
                            'payload_mode' => 'schema_form',
                            'payload' => [
                                'power' => true,
                                'brightness' => 100,
                                'color_hex' => '#FFFF00',
                                'effect' => 'solid',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'command-purple',
                    'type' => 'command',
                    'data' => [
                        'config' => [
                            'target' => [
                                'device_id' => $targetDeviceId,
                                'topic_id' => $targetTopicId,
                            ],
                            'payload_mode' => 'schema_form',
                            'payload' => [
                                'power' => true,
                                'brightness' => 100,
                                'color_hex' => '#800080',
                                'effect' => 'solid',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'command-blue',
                    'type' => 'command',
                    'data' => [
                        'config' => [
                            'target' => [
                                'device_id' => $targetDeviceId,
                                'topic_id' => $targetTopicId,
                            ],
                            'payload_mode' => 'schema_form',
                            'payload' => [
                                'power' => true,
                                'brightness' => 100,
                                'color_hex' => '#0000FF',
                                'effect' => 'solid',
                            ],
                        ],
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'edge-trigger-yellow', 'source' => 'trigger-power-l1', 'target' => 'condition-yellow'],
                ['id' => 'edge-trigger-purple', 'source' => 'trigger-power-l1', 'target' => 'condition-purple'],
                ['id' => 'edge-trigger-blue', 'source' => 'trigger-power-l1', 'target' => 'condition-blue'],
                ['id' => 'edge-yellow-command', 'source' => 'condition-yellow', 'target' => 'command-yellow'],
                ['id' => 'edge-purple-command', 'source' => 'condition-purple', 'target' => 'command-purple'],
                ['id' => 'edge-blue-command', 'source' => 'condition-blue', 'target' => 'command-blue'],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    private function resolvePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $resolved = (int) $value;

        return $resolved > 0 ? $resolved : null;
    }
}
