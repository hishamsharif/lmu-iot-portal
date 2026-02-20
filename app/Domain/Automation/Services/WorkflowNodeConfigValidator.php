<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Data\WorkflowGraph;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\DeviceControl\Services\CommandPayloadResolver;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Support\Arr;
use RuntimeException;

class WorkflowNodeConfigValidator
{
    public function __construct(
        private readonly CommandPayloadResolver $commandPayloadResolver,
        private readonly WorkflowQueryExecutor $workflowQueryExecutor,
    ) {}

    public function validate(AutomationWorkflow $workflow, WorkflowGraph $graph): void
    {
        $organizationId = (int) $workflow->organization_id;

        foreach ($graph->nodes as $node) {
            $nodeType = Arr::get($node, 'type');
            $nodeId = Arr::get($node, 'id');
            $resolvedNodeId = is_string($nodeId) && $nodeId !== '' ? $nodeId : 'unknown-node';

            if (! is_string($nodeType) || $nodeType === '') {
                throw new RuntimeException("Node [{$resolvedNodeId}] has an invalid type.");
            }

            if ($nodeType === 'telemetry-trigger') {
                $this->validateTelemetryTriggerNode($organizationId, $resolvedNodeId, $node);

                continue;
            }

            if ($nodeType === 'condition') {
                $this->validateConditionNode($resolvedNodeId, $node);

                continue;
            }

            if ($nodeType === 'command') {
                $this->validateCommandNode($organizationId, $resolvedNodeId, $node);

                continue;
            }

            if ($nodeType === 'query') {
                $this->validateQueryNode($organizationId, $resolvedNodeId, $node);

                continue;
            }

            if ($nodeType === 'alert') {
                $this->validateAlertNode($resolvedNodeId, $node);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateTelemetryTriggerNode(int $organizationId, string $nodeId, array $node): void
    {
        $config = Arr::get($node, 'data.config');
        if (! is_array($config)) {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] requires a configuration.");
        }

        $mode = Arr::get($config, 'mode');
        if ($mode !== 'event') {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] must use event mode.");
        }

        $source = Arr::get($config, 'source');
        if (! is_array($source)) {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] must define a source.");
        }

        $deviceId = $this->resolvePositiveInt($source['device_id'] ?? null);
        $topicId = $this->resolvePositiveInt($source['topic_id'] ?? null);
        $parameterDefinitionId = $this->resolvePositiveInt($source['parameter_definition_id'] ?? null);

        if ($deviceId === null || $topicId === null || $parameterDefinitionId === null) {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] is missing source device, topic, or parameter.");
        }

        $device = Device::query()
            ->where('organization_id', $organizationId)
            ->find($deviceId);

        $sourceSchemaVersionId = $device instanceof Device
            ? $this->resolvePositiveInt($device->getAttribute('device_schema_version_id'))
            : null;

        if (! $device instanceof Device || $sourceSchemaVersionId === null) {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] references an invalid source device.");
        }

        $topic = SchemaVersionTopic::query()
            ->whereKey($topicId)
            ->where('device_schema_version_id', $sourceSchemaVersionId)
            ->where('direction', TopicDirection::Publish->value)
            ->first();

        if (! $topic instanceof SchemaVersionTopic) {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] references an invalid publish topic.");
        }

        $parameter = ParameterDefinition::query()
            ->whereKey($parameterDefinitionId)
            ->where('schema_version_topic_id', $topic->id)
            ->where('is_active', true)
            ->first();

        if (! $parameter instanceof ParameterDefinition) {
            throw new RuntimeException("Telemetry trigger node [{$nodeId}] references an invalid telemetry parameter.");
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateConditionNode(string $nodeId, array $node): void
    {
        $config = Arr::get($node, 'data.config');
        if (! is_array($config)) {
            throw new RuntimeException("Condition node [{$nodeId}] requires a configuration.");
        }

        $mode = Arr::get($config, 'mode');
        if (! in_array($mode, ['guided', 'json_logic'], true)) {
            throw new RuntimeException("Condition node [{$nodeId}] has an invalid mode.");
        }

        $jsonLogic = Arr::get($config, 'json_logic');
        if (! is_array($jsonLogic) || $jsonLogic === [] || ! Arr::isAssoc($jsonLogic) || count($jsonLogic) !== 1) {
            throw new RuntimeException("Condition node [{$nodeId}] must define valid JSON logic.");
        }

        if ($mode !== 'guided') {
            return;
        }

        $guided = Arr::get($config, 'guided');
        if (! is_array($guided)) {
            throw new RuntimeException("Condition node [{$nodeId}] guided mode requires guided settings.");
        }

        $left = Arr::get($guided, 'left');
        $operator = Arr::get($guided, 'operator');
        $right = Arr::get($guided, 'right');

        if (! in_array($left, ['trigger.value', 'query.value'], true)) {
            throw new RuntimeException("Condition node [{$nodeId}] guided left operand must be trigger.value or query.value.");
        }

        if (! in_array($operator, ['>', '>=', '<', '<=', '==', '!='], true)) {
            throw new RuntimeException("Condition node [{$nodeId}] guided operator is invalid.");
        }

        if (! is_numeric($right)) {
            throw new RuntimeException("Condition node [{$nodeId}] guided threshold must be numeric.");
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateCommandNode(int $organizationId, string $nodeId, array $node): void
    {
        $config = Arr::get($node, 'data.config');
        if (! is_array($config)) {
            throw new RuntimeException("Command node [{$nodeId}] requires a configuration.");
        }

        $payloadMode = Arr::get($config, 'payload_mode');
        if ($payloadMode !== 'schema_form') {
            throw new RuntimeException("Command node [{$nodeId}] must use schema_form payload mode.");
        }

        $target = Arr::get($config, 'target');
        if (! is_array($target)) {
            throw new RuntimeException("Command node [{$nodeId}] must define a target.");
        }

        $targetDeviceId = $this->resolvePositiveInt($target['device_id'] ?? null);
        $targetTopicId = $this->resolvePositiveInt($target['topic_id'] ?? null);

        if ($targetDeviceId === null || $targetTopicId === null) {
            throw new RuntimeException("Command node [{$nodeId}] is missing target device or topic.");
        }

        $payload = Arr::get($config, 'payload');
        if (! is_array($payload)) {
            throw new RuntimeException("Command node [{$nodeId}] must define a payload object.");
        }

        $targetDevice = Device::query()
            ->where('organization_id', $organizationId)
            ->find($targetDeviceId);

        $targetSchemaVersionId = $targetDevice instanceof Device
            ? $this->resolvePositiveInt($targetDevice->getAttribute('device_schema_version_id'))
            : null;

        if (! $targetDevice instanceof Device || $targetSchemaVersionId === null) {
            throw new RuntimeException("Command node [{$nodeId}] references an invalid target device.");
        }

        $topic = SchemaVersionTopic::query()
            ->whereKey($targetTopicId)
            ->where('device_schema_version_id', $targetSchemaVersionId)
            ->where('direction', TopicDirection::Subscribe->value)
            ->first();

        if (! $topic instanceof SchemaVersionTopic) {
            throw new RuntimeException("Command node [{$nodeId}] references an invalid subscribe topic.");
        }

        $errors = $this->commandPayloadResolver->validatePayload($topic, $this->normalizeStringKeyArray($payload));
        if ($errors !== []) {
            $failedKeys = implode(', ', array_keys($errors));

            throw new RuntimeException("Command node [{$nodeId}] has invalid payload values: {$failedKeys}.");
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateQueryNode(int $organizationId, string $nodeId, array $node): void
    {
        $config = Arr::get($node, 'data.config');
        if (! is_array($config)) {
            throw new RuntimeException("Query node [{$nodeId}] requires a configuration.");
        }

        try {
            $this->workflowQueryExecutor->validateConfig($organizationId, $this->normalizeStringKeyArray($config));
        } catch (RuntimeException $exception) {
            throw new RuntimeException("Query node [{$nodeId}] {$exception->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateAlertNode(string $nodeId, array $node): void
    {
        $config = Arr::get($node, 'data.config');

        if (! is_array($config) || $config === []) {
            return;
        }

        $alertRuntimeKeys = ['channel', 'recipients', 'subject', 'body', 'cooldown'];
        $hasRuntimeConfig = false;

        foreach ($alertRuntimeKeys as $alertRuntimeKey) {
            if (array_key_exists($alertRuntimeKey, $config)) {
                $hasRuntimeConfig = true;

                break;
            }
        }

        if (! $hasRuntimeConfig) {
            return;
        }

        $channel = Arr::get($config, 'channel');
        if ($channel !== 'email') {
            throw new RuntimeException("Alert node [{$nodeId}] channel must be email.");
        }

        $recipients = Arr::get($config, 'recipients');
        if (! is_array($recipients) || $recipients === []) {
            throw new RuntimeException("Alert node [{$nodeId}] requires at least one recipient.");
        }

        $hasValidRecipient = false;

        foreach ($recipients as $recipient) {
            if (! is_string($recipient) || trim($recipient) === '') {
                continue;
            }

            if (filter_var(trim($recipient), FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException("Alert node [{$nodeId}] recipients must be valid email addresses.");
            }

            $hasValidRecipient = true;
        }

        if (! $hasValidRecipient) {
            throw new RuntimeException("Alert node [{$nodeId}] requires at least one recipient.");
        }

        $subject = Arr::get($config, 'subject');
        $body = Arr::get($config, 'body');

        if (! is_string($subject) || trim($subject) === '') {
            throw new RuntimeException("Alert node [{$nodeId}] subject is required.");
        }

        if (! is_string($body) || trim($body) === '') {
            throw new RuntimeException("Alert node [{$nodeId}] body is required.");
        }

        $cooldown = Arr::get($config, 'cooldown');
        if (! is_array($cooldown)) {
            throw new RuntimeException("Alert node [{$nodeId}] cooldown configuration is required.");
        }

        $cooldownValue = $this->resolvePositiveInt($cooldown['value'] ?? null);
        $cooldownUnit = $cooldown['unit'] ?? null;

        if ($cooldownValue === null || ! is_string($cooldownUnit) || ! in_array($cooldownUnit, ['minute', 'hour', 'day'], true)) {
            throw new RuntimeException("Alert node [{$nodeId}] cooldown must include positive value and valid unit.");
        }
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

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeStringKeyArray(array $payload): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
