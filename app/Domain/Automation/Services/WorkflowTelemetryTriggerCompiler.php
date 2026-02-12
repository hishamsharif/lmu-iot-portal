<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Data\WorkflowGraph;
use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Support\Arr;

class WorkflowTelemetryTriggerCompiler
{
    public function compile(
        AutomationWorkflow $workflow,
        AutomationWorkflowVersion $workflowVersion,
        WorkflowGraph $graph,
    ): void {
        AutomationTelemetryTrigger::query()
            ->where('workflow_version_id', $workflowVersion->id)
            ->delete();

        $organizationId = (int) $workflow->organization_id;
        $compiledRows = [];

        foreach ($graph->nodes as $node) {
            if (Arr::get($node, 'type') !== 'telemetry-trigger') {
                continue;
            }

            $source = $this->extractTriggerSource($node);
            if ($source === null) {
                continue;
            }

            $compiledRow = $this->resolveCompiledRow($organizationId, $workflowVersion->id, $source);
            if ($compiledRow === null) {
                continue;
            }

            $dedupeKey = "{$compiledRow['device_id']}:{$compiledRow['schema_version_topic_id']}";
            $compiledRows[$dedupeKey] = $compiledRow;
        }

        foreach ($compiledRows as $compiledRow) {
            AutomationTelemetryTrigger::query()->create($compiledRow);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{device_id: int, topic_id: int, parameter_definition_id: int}|null
     */
    private function extractTriggerSource(array $node): ?array
    {
        $config = Arr::get($node, 'data.config');
        if (! is_array($config)) {
            return null;
        }

        if (Arr::get($config, 'mode') !== 'event') {
            return null;
        }

        $source = Arr::get($config, 'source');
        if (! is_array($source)) {
            return null;
        }

        $deviceId = $this->resolvePositiveInt($source['device_id'] ?? null);
        $topicId = $this->resolvePositiveInt($source['topic_id'] ?? null);
        $parameterDefinitionId = $this->resolvePositiveInt($source['parameter_definition_id'] ?? null);

        if ($deviceId === null || $topicId === null || $parameterDefinitionId === null) {
            return null;
        }

        return [
            'device_id' => $deviceId,
            'topic_id' => $topicId,
            'parameter_definition_id' => $parameterDefinitionId,
        ];
    }

    /**
     * @param  array{device_id: int, topic_id: int, parameter_definition_id: int}  $source
     * @return array{
     *     organization_id: int,
     *     workflow_version_id: int,
     *     device_id: int,
     *     device_type_id: int|null,
     *     schema_version_topic_id: int,
     *     filter_expression: null
     * }|null
     */
    private function resolveCompiledRow(int $organizationId, int $workflowVersionId, array $source): ?array
    {
        $device = Device::query()
            ->where('organization_id', $organizationId)
            ->find($source['device_id']);

        $schemaVersionId = $device instanceof Device
            ? $this->resolvePositiveInt($device->getAttribute('device_schema_version_id'))
            : null;

        if (! $device instanceof Device || $schemaVersionId === null) {
            return null;
        }

        $topic = SchemaVersionTopic::query()
            ->whereKey($source['topic_id'])
            ->where('device_schema_version_id', $schemaVersionId)
            ->where('direction', TopicDirection::Publish->value)
            ->first();

        if (! $topic instanceof SchemaVersionTopic) {
            return null;
        }

        $parameter = ParameterDefinition::query()
            ->whereKey($source['parameter_definition_id'])
            ->where('schema_version_topic_id', $topic->id)
            ->where('is_active', true)
            ->first();

        if (! $parameter instanceof ParameterDefinition) {
            return null;
        }

        return [
            'organization_id' => $organizationId,
            'workflow_version_id' => $workflowVersionId,
            'device_id' => $device->id,
            'device_type_id' => $device->device_type_id,
            'schema_version_topic_id' => $topic->id,
            'filter_expression' => null,
        ];
    }

    private function resolvePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $resolved = (int) $value;

        return $resolved > 0 ? $resolved : null;
    }
}
