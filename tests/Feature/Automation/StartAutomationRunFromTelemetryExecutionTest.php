<?php

declare(strict_types=1);

use App\Domain\Automation\Jobs\StartAutomationRunFromTelemetry;
use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\Mqtt\MqttCommandPublisher;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createExecutionFixture(float $voltage): array
{
    $organization = Organization::factory()->create();

    $sourceDeviceType = DeviceType::factory()->forOrganization($organization->id)->mqtt()->create();
    $sourceSchema = DeviceSchema::factory()->forDeviceType($sourceDeviceType)->create();
    $sourceSchemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $sourceSchema->id,
    ]);

    $sourceTopic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $sourceSchemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    $sourceParameter = ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $sourceTopic->id,
        'key' => 'V1',
        'json_path' => 'voltages.V1',
        'type' => ParameterDataType::Decimal,
        'required' => true,
        'is_active' => true,
    ]);

    $sourceDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $sourceDeviceType->id,
        'device_schema_version_id' => $sourceSchemaVersion->id,
        'name' => 'Energy Meter',
    ]);

    $targetDeviceType = DeviceType::factory()->forOrganization($organization->id)->mqtt()->create();
    $targetSchema = DeviceSchema::factory()->forDeviceType($targetDeviceType)->create();
    $targetSchemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $targetSchema->id,
    ]);

    $targetTopic = SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $targetSchemaVersion->id,
        'key' => 'control',
        'suffix' => 'control',
    ]);

    ParameterDefinition::factory()->subscribe()->create([
        'schema_version_topic_id' => $targetTopic->id,
        'key' => 'power',
        'json_path' => 'power',
        'type' => ParameterDataType::Boolean,
        'required' => true,
        'default_value' => false,
        'is_active' => true,
    ]);

    ParameterDefinition::factory()->subscribe()->create([
        'schema_version_topic_id' => $targetTopic->id,
        'key' => 'color',
        'json_path' => 'color',
        'type' => ParameterDataType::String,
        'required' => true,
        'default_value' => 'RED',
        'validation_rules' => ['enum' => ['RED', 'GREEN', 'BLUE']],
        'is_active' => true,
    ]);

    $targetDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $targetDeviceType->id,
        'device_schema_version_id' => $targetSchemaVersion->id,
        'name' => 'RGB Strip',
    ]);

    $workflow = AutomationWorkflow::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $graph = [
        'version' => 1,
        'nodes' => [
            [
                'id' => 'trigger-1',
                'type' => 'telemetry-trigger',
                'data' => [
                    'config' => [
                        'mode' => 'event',
                        'source' => [
                            'device_id' => $sourceDevice->id,
                            'topic_id' => $sourceTopic->id,
                            'parameter_definition_id' => $sourceParameter->id,
                        ],
                    ],
                ],
            ],
            [
                'id' => 'condition-1',
                'type' => 'condition',
                'data' => [
                    'config' => [
                        'mode' => 'guided',
                        'guided' => [
                            'left' => 'trigger.value',
                            'operator' => '>',
                            'right' => 20,
                        ],
                        'json_logic' => [
                            '>' => [
                                ['var' => 'trigger.value'],
                                20,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'command-1',
                'type' => 'command',
                'data' => [
                    'config' => [
                        'target' => [
                            'device_id' => $targetDevice->id,
                            'topic_id' => $targetTopic->id,
                        ],
                        'payload_mode' => 'schema_form',
                        'payload' => [
                            'power' => true,
                            'color' => 'RED',
                        ],
                    ],
                ],
            ],
        ],
        'edges' => [
            ['id' => 'edge-1', 'source' => 'trigger-1', 'target' => 'condition-1'],
            ['id' => 'edge-2', 'source' => 'condition-1', 'target' => 'command-1'],
        ],
    ];

    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
        'version' => 1,
        'graph_json' => $graph,
    ]);

    $workflow->update(['active_version_id' => $version->id]);

    AutomationTelemetryTrigger::factory()->create([
        'organization_id' => $organization->id,
        'workflow_version_id' => $version->id,
        'device_id' => $sourceDevice->id,
        'device_type_id' => $sourceDevice->device_type_id,
        'schema_version_topic_id' => $sourceTopic->id,
    ]);

    $telemetryLog = DeviceTelemetryLog::factory()
        ->forDevice($sourceDevice)
        ->forTopic($sourceTopic)
        ->create([
            'transformed_values' => [
                'voltages' => ['V1' => $voltage],
                'V1' => $voltage,
            ],
            'raw_payload' => [
                'voltages' => ['V1' => $voltage],
            ],
        ]);

    return [
        'workflow' => $workflow,
        'version' => $version,
        'telemetryLog' => $telemetryLog,
        'targetDevice' => $targetDevice,
        'targetTopic' => $targetTopic,
    ];
}

function bindFakeMqttPublisherForAutomationExecution(): void
{
    $fakePublisher = new class implements MqttCommandPublisher
    {
        /** @var array<int, array{topic: string, payload: string, host: string, port: int}> */
        public array $published = [];

        public function publish(string $mqttTopic, string $payload, string $host, int $port): void
        {
            $this->published[] = [
                'topic' => $mqttTopic,
                'payload' => $payload,
                'host' => $host,
                'port' => $port,
            ];
        }
    };

    app()->instance(MqttCommandPublisher::class, $fakePublisher);
}

it('executes condition and command nodes when telemetry condition passes', function (): void {
    bindFakeMqttPublisherForAutomationExecution();

    $fixture = createExecutionFixture(voltage: 120.5);

    (new StartAutomationRunFromTelemetry(
        workflowVersionId: $fixture['version']->id,
        telemetryLogId: $fixture['telemetryLog']->id,
    ))->handle();

    $run = $fixture['workflow']->runs()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run?->status->value)->toBe('completed')
        ->and($run?->started_at)->not->toBeNull()
        ->and($run?->finished_at)->not->toBeNull()
        ->and($run?->trigger_payload)->toBeArray()
        ->and($run?->trigger_payload)->toHaveKey('event_correlation_id')
        ->and($run?->trigger_payload)->toHaveKey('run_correlation_id');

    expect($run?->steps()->where('node_id', 'trigger-1')->where('status', 'completed')->exists())->toBeTrue()
        ->and($run?->steps()->where('node_id', 'condition-1')->where('status', 'completed')->exists())->toBeTrue()
        ->and($run?->steps()->where('node_id', 'command-1')->where('status', 'completed')->exists())->toBeTrue();

    $commandLog = DeviceCommandLog::query()->latest('id')->first();

    expect($commandLog)->not->toBeNull()
        ->and($commandLog?->device_id)->toBe($fixture['targetDevice']->id)
        ->and($commandLog?->schema_version_topic_id)->toBe($fixture['targetTopic']->id)
        ->and($commandLog?->command_payload)->toMatchArray([
            'power' => true,
            'color' => 'RED',
        ])
        ->and($commandLog?->status)->toBe(CommandStatus::Sent);
});

it('does not dispatch command when condition evaluates to false', function (): void {
    bindFakeMqttPublisherForAutomationExecution();

    $fixture = createExecutionFixture(voltage: 10.0);

    (new StartAutomationRunFromTelemetry(
        workflowVersionId: $fixture['version']->id,
        telemetryLogId: $fixture['telemetryLog']->id,
    ))->handle();

    $run = $fixture['workflow']->runs()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run?->status->value)->toBe('completed')
        ->and($run?->trigger_payload)->toBeArray()
        ->and($run?->trigger_payload)->toHaveKey('event_correlation_id')
        ->and($run?->trigger_payload)->toHaveKey('run_correlation_id')
        ->and($run?->steps()->where('node_id', 'condition-1')->where('status', 'completed')->exists())->toBeTrue()
        ->and($run?->steps()->where('node_id', 'command-1')->exists())->toBeFalse()
        ->and(DeviceCommandLog::query()->count())->toBe(0);
});
