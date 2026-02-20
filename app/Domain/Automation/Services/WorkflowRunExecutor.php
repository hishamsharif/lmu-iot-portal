<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Data\WorkflowExecutionResult;
use App\Domain\Automation\Data\WorkflowGraph;
use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Services\DeviceCommandDispatcher;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\DeviceSchema\Services\JsonLogicEvaluator;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Log\LogManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use RuntimeException;

class WorkflowRunExecutor
{
    private const ALERT_DEFAULT_COOLDOWN_VALUE = 30;

    private const ALERT_DEFAULT_COOLDOWN_UNIT = 'minute';

    public function __construct(
        private readonly JsonLogicEvaluator $jsonLogicEvaluator,
        private readonly DeviceCommandDispatcher $deviceCommandDispatcher,
        private readonly WorkflowQueryExecutor $workflowQueryExecutor,
        private readonly WorkflowAlertDispatcher $workflowAlertDispatcher,
        private readonly CacheManager $cacheManager,
        private readonly LogManager $logManager,
    ) {}

    public function executeTelemetryRun(
        AutomationRun $run,
        AutomationWorkflowVersion $workflowVersion,
        DeviceTelemetryLog $telemetryLog,
        string $runCorrelationId,
    ): WorkflowExecutionResult {
        $graph = WorkflowGraph::fromArray($this->resolveGraphPayload($workflowVersion));

        [$nodesById, $edgesBySource] = $this->buildGraphIndexes($graph);
        $triggerContexts = $this->resolveTriggerContexts($graph, $telemetryLog);
        $triggerPayload = $this->resolveAssociativeArray($run->getAttribute('trigger_payload'));
        $eventCorrelationId = Arr::get($triggerPayload, 'event_correlation_id');
        $telemetryLogId = $this->resolveKeyAsString($telemetryLog->getKey());

        $baseLogContext = [
            'event_correlation_id' => is_string($eventCorrelationId) && $eventCorrelationId !== '' ? $eventCorrelationId : null,
            'run_correlation_id' => $runCorrelationId,
            'automation_run_id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'workflow_version_id' => $workflowVersion->id,
            'telemetry_log_id' => $telemetryLogId,
            'trigger_node_matches' => count($triggerContexts),
            'graph_node_count' => count($graph->nodes),
            'graph_edge_count' => count($graph->edges),
        ];

        $this->log()->info('Automation workflow execution started.', $baseLogContext);

        if ($triggerContexts === []) {
            $this->log()->warning('Automation workflow execution found no matching trigger nodes.', $baseLogContext);

            return new WorkflowExecutionResult(
                status: AutomationRunStatus::Completed,
                steps: [],
                error: ['reason' => 'no_matching_trigger_nodes'],
            );
        }

        $hasFailures = false;
        $stepSummaries = [];
        $stepSequence = 0;

        foreach ($triggerContexts as $triggerContext) {
            $triggerNode = $triggerContext['node'];
            $triggerNodeId = Arr::get($triggerNode, 'id');
            $triggerNodeType = Arr::get($triggerNode, 'type');

            if (! is_string($triggerNodeId) || ! is_string($triggerNodeType)) {
                continue;
            }

            $this->recordStep(
                run: $run,
                stepSummaries: $stepSummaries,
                nodeId: $triggerNodeId,
                nodeType: $triggerNodeType,
                status: 'completed',
                input: [
                    'telemetry_log_id' => $telemetryLogId,
                    'device_id' => $telemetryLog->device_id,
                    'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
                ],
                output: $triggerContext['trigger'],
                error: null,
                startedAtMicrotime: microtime(true),
                runCorrelationId: $runCorrelationId,
                stepSequence: $stepSequence,
            );

            foreach ($edgesBySource[$triggerNodeId] ?? [] as $nextNodeId) {
                $this->executeFromNode(
                    run: $run,
                    nodeId: $nextNodeId,
                    nodesById: $nodesById,
                    edgesBySource: $edgesBySource,
                    executionContext: [
                        'trigger' => $triggerContext['trigger'],
                        'payload' => $triggerContext['payload'],
                        'query' => [],
                        'queries' => [],
                    ],
                    stepSummaries: $stepSummaries,
                    hasFailures: $hasFailures,
                    runCorrelationId: $runCorrelationId,
                    stepSequence: $stepSequence,
                );
            }
        }

        $finalStatus = $hasFailures ? AutomationRunStatus::Failed : AutomationRunStatus::Completed;

        $this->log()->info('Automation workflow execution finished.', [
            ...$baseLogContext,
            'status' => $finalStatus->value,
            'step_count' => count($stepSummaries),
            'failed' => $hasFailures,
        ]);

        return new WorkflowExecutionResult(
            status: $finalStatus,
            steps: $stepSummaries,
            error: $hasFailures ? ['reason' => 'node_execution_failed'] : null,
        );
    }

    /**
     * @return array{
     *     0: array<string, array<string, mixed>>,
     *     1: array<string, array<int, string>>
     * }
     */
    private function buildGraphIndexes(WorkflowGraph $graph): array
    {
        $nodesById = [];

        foreach ($graph->nodes as $node) {
            $nodeId = Arr::get($node, 'id');

            if (! is_string($nodeId) || $nodeId === '') {
                continue;
            }

            $nodesById[$nodeId] = $node;
        }

        $edgesBySource = [];

        foreach ($graph->edges as $edge) {
            $source = Arr::get($edge, 'source');
            $target = Arr::get($edge, 'target');

            if (! is_string($source) || ! is_string($target)) {
                continue;
            }

            if (! isset($nodesById[$source], $nodesById[$target])) {
                continue;
            }

            $edgesBySource[$source][] = $target;
        }

        return [$nodesById, $edgesBySource];
    }

    /**
     * @return array<int, array{
     *     node: array<string, mixed>,
     *     trigger: array<string, mixed>,
     *     payload: array<string, mixed>
     * }>
     */
    private function resolveTriggerContexts(WorkflowGraph $graph, DeviceTelemetryLog $telemetryLog): array
    {
        $payload = $this->resolveAssociativeArray($telemetryLog->getAttribute('transformed_values'));
        $matchedTriggerNodes = [];
        $parameterIds = [];

        foreach ($graph->nodes as $node) {
            if (Arr::get($node, 'type') !== 'telemetry-trigger') {
                continue;
            }

            $sourceDeviceId = $this->resolvePositiveInt(Arr::get($node, 'data.config.source.device_id'));
            $sourceTopicId = $this->resolvePositiveInt(Arr::get($node, 'data.config.source.topic_id'));
            $parameterDefinitionId = $this->resolvePositiveInt(Arr::get($node, 'data.config.source.parameter_definition_id'));

            if ($sourceDeviceId === null || $sourceTopicId === null || $parameterDefinitionId === null) {
                continue;
            }

            if ((int) $telemetryLog->device_id !== $sourceDeviceId) {
                continue;
            }

            if ((int) $telemetryLog->schema_version_topic_id !== $sourceTopicId) {
                continue;
            }

            $matchedTriggerNodes[] = [
                'node' => $node,
                'parameter_definition_id' => $parameterDefinitionId,
            ];

            $parameterIds[] = $parameterDefinitionId;
        }

        if ($matchedTriggerNodes === [] || $parameterIds === []) {
            return [];
        }

        $parameters = ParameterDefinition::query()
            ->whereIn('id', array_values(array_unique($parameterIds)))
            ->get()
            ->keyBy('id');

        $contexts = [];

        foreach ($matchedTriggerNodes as $matchedTriggerNode) {
            $parameterDefinitionId = $matchedTriggerNode['parameter_definition_id'];
            $parameter = $parameters->get($parameterDefinitionId);

            if (! $parameter instanceof ParameterDefinition) {
                continue;
            }

            $sourceTopicId = $this->resolvePositiveInt(Arr::get($matchedTriggerNode['node'], 'data.config.source.topic_id'));
            if ($sourceTopicId === null || (int) $parameter->schema_version_topic_id !== $sourceTopicId) {
                continue;
            }

            $contexts[] = [
                'node' => $matchedTriggerNode['node'],
                'trigger' => [
                    'value' => $this->resolveTelemetryValue($payload, $parameter),
                    'parameter_definition_id' => $parameter->id,
                    'parameter_key' => $parameter->key,
                    'device_id' => (int) $telemetryLog->device_id,
                    'schema_version_topic_id' => (int) $telemetryLog->schema_version_topic_id,
                    'recorded_at' => $telemetryLog->recorded_at->format(DATE_ATOM),
                ],
                'payload' => $payload,
            ];
        }

        return $contexts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodesById
     * @param  array<string, array<int, string>>  $edgesBySource
     * @param  array<string, mixed>  $executionContext
     * @param  array<int, array<string, mixed>>  $stepSummaries
     */
    private function executeFromNode(
        AutomationRun $run,
        string $nodeId,
        array $nodesById,
        array $edgesBySource,
        array $executionContext,
        array &$stepSummaries,
        bool &$hasFailures,
        string $runCorrelationId,
        int &$stepSequence,
    ): void {
        $node = $nodesById[$nodeId] ?? null;

        if (! is_array($node)) {
            return;
        }

        $nodeType = Arr::get($node, 'type');

        if (! is_string($nodeType) || $nodeType === '') {
            return;
        }

        $startedAt = microtime(true);

        if ($nodeType === 'condition') {
            $result = $this->runConditionNode(
                node: $node,
                executionContext: $executionContext,
                runCorrelationId: $runCorrelationId,
                nodeId: $nodeId,
            );

            $this->recordStep(
                run: $run,
                stepSummaries: $stepSummaries,
                nodeId: $nodeId,
                nodeType: $nodeType,
                status: $result['status'],
                input: ['context' => $executionContext],
                output: $result['output'],
                error: $result['error'],
                startedAtMicrotime: $startedAt,
                runCorrelationId: $runCorrelationId,
                stepSequence: $stepSequence,
            );

            if ($result['status'] === 'failed') {
                $hasFailures = true;

                return;
            }

            if ($result['continue'] !== true) {
                return;
            }

            foreach ($edgesBySource[$nodeId] ?? [] as $nextNodeId) {
                $this->executeFromNode(
                    run: $run,
                    nodeId: $nextNodeId,
                    nodesById: $nodesById,
                    edgesBySource: $edgesBySource,
                    executionContext: $executionContext,
                    stepSummaries: $stepSummaries,
                    hasFailures: $hasFailures,
                    runCorrelationId: $runCorrelationId,
                    stepSequence: $stepSequence,
                );
            }

            return;
        }

        if ($nodeType === 'query') {
            $result = $this->runQueryNode(
                run: $run,
                node: $node,
                executionContext: $executionContext,
                runCorrelationId: $runCorrelationId,
                nodeId: $nodeId,
            );

            $this->recordStep(
                run: $run,
                stepSummaries: $stepSummaries,
                nodeId: $nodeId,
                nodeType: $nodeType,
                status: $result['status'],
                input: ['context' => $executionContext],
                output: $result['output'],
                error: $result['error'],
                startedAtMicrotime: $startedAt,
                runCorrelationId: $runCorrelationId,
                stepSequence: $stepSequence,
            );

            if ($result['status'] === 'failed') {
                $hasFailures = true;

                return;
            }

            foreach ($edgesBySource[$nodeId] ?? [] as $nextNodeId) {
                $this->executeFromNode(
                    run: $run,
                    nodeId: $nextNodeId,
                    nodesById: $nodesById,
                    edgesBySource: $edgesBySource,
                    executionContext: $result['execution_context'],
                    stepSummaries: $stepSummaries,
                    hasFailures: $hasFailures,
                    runCorrelationId: $runCorrelationId,
                    stepSequence: $stepSequence,
                );
            }

            return;
        }

        if ($nodeType === 'command') {
            $result = $this->runCommandNode(
                run: $run,
                node: $node,
                runCorrelationId: $runCorrelationId,
                nodeId: $nodeId,
            );

            $this->recordStep(
                run: $run,
                stepSummaries: $stepSummaries,
                nodeId: $nodeId,
                nodeType: $nodeType,
                status: $result['status'],
                input: ['context' => $executionContext],
                output: $result['output'],
                error: $result['error'],
                startedAtMicrotime: $startedAt,
                runCorrelationId: $runCorrelationId,
                stepSequence: $stepSequence,
            );

            if ($result['status'] === 'failed') {
                $hasFailures = true;

                return;
            }

            foreach ($edgesBySource[$nodeId] ?? [] as $nextNodeId) {
                $this->executeFromNode(
                    run: $run,
                    nodeId: $nextNodeId,
                    nodesById: $nodesById,
                    edgesBySource: $edgesBySource,
                    executionContext: $executionContext,
                    stepSummaries: $stepSummaries,
                    hasFailures: $hasFailures,
                    runCorrelationId: $runCorrelationId,
                    stepSequence: $stepSequence,
                );
            }

            return;
        }

        if ($nodeType === 'alert') {
            $result = $this->runAlertNode(
                run: $run,
                node: $node,
                executionContext: $executionContext,
                runCorrelationId: $runCorrelationId,
                nodeId: $nodeId,
            );

            $this->recordStep(
                run: $run,
                stepSummaries: $stepSummaries,
                nodeId: $nodeId,
                nodeType: $nodeType,
                status: $result['status'],
                input: ['context' => $executionContext],
                output: $result['output'],
                error: $result['error'],
                startedAtMicrotime: $startedAt,
                runCorrelationId: $runCorrelationId,
                stepSequence: $stepSequence,
            );

            if ($result['status'] === 'failed') {
                $hasFailures = true;

                return;
            }

            foreach ($edgesBySource[$nodeId] ?? [] as $nextNodeId) {
                $this->executeFromNode(
                    run: $run,
                    nodeId: $nextNodeId,
                    nodesById: $nodesById,
                    edgesBySource: $edgesBySource,
                    executionContext: $result['execution_context'],
                    stepSummaries: $stepSummaries,
                    hasFailures: $hasFailures,
                    runCorrelationId: $runCorrelationId,
                    stepSequence: $stepSequence,
                );
            }

            return;
        }

        $this->recordStep(
            run: $run,
            stepSummaries: $stepSummaries,
            nodeId: $nodeId,
            nodeType: $nodeType,
            status: 'skipped',
            input: ['context' => $executionContext],
            output: ['reason' => 'node_type_not_implemented'],
            error: null,
            startedAtMicrotime: $startedAt,
            runCorrelationId: $runCorrelationId,
            stepSequence: $stepSequence,
        );

        foreach ($edgesBySource[$nodeId] ?? [] as $nextNodeId) {
            $this->executeFromNode(
                run: $run,
                nodeId: $nextNodeId,
                nodesById: $nodesById,
                edgesBySource: $edgesBySource,
                executionContext: $executionContext,
                stepSummaries: $stepSummaries,
                hasFailures: $hasFailures,
                runCorrelationId: $runCorrelationId,
                stepSequence: $stepSequence,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $executionContext
     * @return array{
     *     status: 'completed'|'failed',
     *     continue: bool,
     *     output: array<string, mixed>,
     *     error: array<string, mixed>|null
     * }
     */
    private function runConditionNode(
        array $node,
        array $executionContext,
        string $runCorrelationId,
        string $nodeId,
    ): array {
        $jsonLogic = Arr::get($node, 'data.config.json_logic');

        if (! is_array($jsonLogic) || $jsonLogic === []) {
            $this->log()->warning('Condition node failed due to missing json_logic configuration.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
            ]);

            return [
                'status' => 'failed',
                'continue' => false,
                'output' => [],
                'error' => ['reason' => 'condition_config_missing_json_logic'],
            ];
        }

        $evaluationData = [
            'trigger' => Arr::get($executionContext, 'trigger', []),
            'query' => Arr::get($executionContext, 'query', []),
            'queries' => Arr::get($executionContext, 'queries', []),
            'payload' => Arr::get($executionContext, 'payload', []),
        ];

        $payload = Arr::get($executionContext, 'payload');
        if (is_array($payload)) {
            $evaluationData = array_merge($payload, $evaluationData);
        }

        $rawEvaluationResult = $this->jsonLogicEvaluator->evaluate($jsonLogic, $evaluationData);
        $passed = $this->resolveBoolean($rawEvaluationResult);

        $this->log()->info('Condition node evaluated.', [
            'run_correlation_id' => $runCorrelationId,
            'node_id' => $nodeId,
            'passed' => $passed,
            'raw_result_type' => get_debug_type($rawEvaluationResult),
            'trigger_value' => Arr::get($executionContext, 'trigger.value'),
            'query_value' => Arr::get($executionContext, 'query.value'),
        ]);

        return [
            'status' => 'completed',
            'continue' => $passed,
            'output' => [
                'passed' => $passed,
                'evaluation_result' => $rawEvaluationResult,
            ],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $executionContext
     * @return array{
     *     status: 'completed'|'failed',
     *     execution_context: array<string, mixed>,
     *     output: array<string, mixed>,
     *     error: array<string, mixed>|null
     * }
     */
    private function runQueryNode(
        AutomationRun $run,
        array $node,
        array $executionContext,
        string $runCorrelationId,
        string $nodeId,
    ): array {
        $config = Arr::get($node, 'data.config');

        if (! is_array($config)) {
            $this->log()->warning('Query node failed due to missing configuration.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
            ]);

            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => ['reason' => 'query_config_missing'],
            ];
        }

        try {
            $normalizedConfig = $this->normalizeStringKeyArray($config);

            $result = $this->workflowQueryExecutor->execute(
                run: $run,
                config: $normalizedConfig,
                executionContext: $executionContext,
            );
        } catch (\Throwable $exception) {
            $this->log()->warning('Query node failed during execution.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => [
                    'reason' => 'query_execution_failed',
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        $nextExecutionContext = $this->applyQueryResultToExecutionContext($executionContext, $nodeId, $result);

        $sources = Arr::get($result, 'sources');
        $sourceCount = is_array($sources) ? count($sources) : 0;

        $this->log()->info('Query node executed successfully.', [
            'run_correlation_id' => $runCorrelationId,
            'node_id' => $nodeId,
            'query_value' => Arr::get($result, 'value'),
            'window_start' => Arr::get($result, 'window.start'),
            'window_end' => Arr::get($result, 'window.end'),
            'source_count' => $sourceCount,
        ]);

        return [
            'status' => 'completed',
            'execution_context' => $nextExecutionContext,
            'output' => $result,
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $executionContext
     * @return array{
     *     status: 'completed'|'failed'|'skipped',
     *     execution_context: array<string, mixed>,
     *     output: array<string, mixed>,
     *     error: array<string, mixed>|null
     * }
     */
    private function runAlertNode(
        AutomationRun $run,
        array $node,
        array $executionContext,
        string $runCorrelationId,
        string $nodeId,
    ): array {
        $config = Arr::get($node, 'data.config');

        if (! is_array($config) || $config === []) {
            return [
                'status' => 'skipped',
                'execution_context' => $executionContext,
                'output' => ['reason' => 'alert_config_missing'],
                'error' => null,
            ];
        }

        $normalizedConfig = $this->normalizeStringKeyArray($config);

        if (! $this->alertConfigHasRuntimeFields($normalizedConfig)) {
            return [
                'status' => 'skipped',
                'execution_context' => $executionContext,
                'output' => ['reason' => 'alert_config_legacy_placeholder'],
                'error' => null,
            ];
        }

        $channel = Arr::get($normalizedConfig, 'channel');
        $subjectTemplate = Arr::get($normalizedConfig, 'subject');
        $bodyTemplate = Arr::get($normalizedConfig, 'body');

        if ($channel !== 'email' || ! is_string($subjectTemplate) || ! is_string($bodyTemplate)) {
            $this->log()->warning('Alert node failed due to incomplete configuration.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
                'channel' => $channel,
            ]);

            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => ['reason' => 'alert_config_incomplete'],
            ];
        }

        try {
            $recipients = $this->resolveAlertRecipients(Arr::get($normalizedConfig, 'recipients'));
        } catch (RuntimeException $exception) {
            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => [
                    'reason' => 'alert_recipients_invalid',
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        if ($recipients === []) {
            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => ['reason' => 'alert_recipients_missing'],
            ];
        }

        $cooldown = $this->resolveAlertCooldown(Arr::get($normalizedConfig, 'cooldown'));
        $templateContext = $this->buildAlertTemplateContext($run, $executionContext, $nodeId, $cooldown);
        $subject = trim($this->interpolateTemplate($subjectTemplate, $templateContext));
        $body = trim($this->interpolateTemplate($bodyTemplate, $templateContext));

        if ($subject === '' || $body === '') {
            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => ['reason' => 'alert_rendered_content_empty'],
            ];
        }

        $cooldownKey = $this->buildAlertCooldownCacheKey($run, $executionContext, $nodeId);
        $cooldownExpiresAt = $this->resolveAlertCooldownExpiration($cooldown);
        $cooldownAllowed = $this->cacheManager->store()->add($cooldownKey, now()->toIso8601String(), $cooldownExpiresAt);

        if ($cooldownAllowed !== true) {
            return [
                'status' => 'skipped',
                'execution_context' => $executionContext,
                'output' => [
                    'reason' => 'alert_cooldown_active',
                    'cooldown_key' => $cooldownKey,
                    'cooldown' => $cooldown,
                ],
                'error' => null,
            ];
        }

        try {
            $dispatchResult = $this->workflowAlertDispatcher->dispatch(
                channel: $channel,
                recipients: $recipients,
                subject: $subject,
                body: $body,
                context: $templateContext,
            );
        } catch (\Throwable $exception) {
            $this->cacheManager->store()->forget($cooldownKey);

            $this->log()->warning('Alert node failed to dispatch.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'execution_context' => $executionContext,
                'output' => [],
                'error' => [
                    'reason' => 'alert_dispatch_failed',
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        $this->log()->info('Alert node dispatched successfully.', [
            'run_correlation_id' => $runCorrelationId,
            'node_id' => $nodeId,
            'recipient_count' => count($recipients),
            'channel' => $channel,
        ]);

        return [
            'status' => 'completed',
            'execution_context' => $executionContext,
            'output' => [
                'channel' => $channel,
                'subject' => $subject,
                'body' => $body,
                'recipients' => $recipients,
                'cooldown' => $cooldown,
                'cooldown_key' => $cooldownKey,
                'dispatch' => $dispatchResult,
            ],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{
     *     status: 'completed'|'failed',
     *     output: array<string, mixed>,
     *     error: array<string, mixed>|null
     * }
     */
    private function runCommandNode(
        AutomationRun $run,
        array $node,
        string $runCorrelationId,
        string $nodeId,
    ): array {
        $targetDeviceId = $this->resolvePositiveInt(Arr::get($node, 'data.config.target.device_id'));
        $targetTopicId = $this->resolvePositiveInt(Arr::get($node, 'data.config.target.topic_id'));
        $payload = Arr::get($node, 'data.config.payload');

        if ($targetDeviceId === null || $targetTopicId === null || ! is_array($payload)) {
            $this->log()->warning('Command node failed due to incomplete configuration.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
            ]);

            return [
                'status' => 'failed',
                'output' => [],
                'error' => ['reason' => 'command_config_incomplete'],
            ];
        }

        $resolvedPayload = $this->normalizeStringKeyArray($payload);

        $device = Device::query()
            ->where('organization_id', (int) $run->organization_id)
            ->find($targetDeviceId);

        $targetSchemaVersionId = $device instanceof Device
            ? $this->resolvePositiveInt($device->getAttribute('device_schema_version_id'))
            : null;

        if (! $device instanceof Device || $targetSchemaVersionId === null) {
            $this->log()->warning('Command node failed due to invalid target device.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
                'target_device_id' => $targetDeviceId,
            ]);

            return [
                'status' => 'failed',
                'output' => [],
                'error' => ['reason' => 'command_target_device_invalid'],
            ];
        }

        $topic = SchemaVersionTopic::query()
            ->whereKey($targetTopicId)
            ->where('device_schema_version_id', $targetSchemaVersionId)
            ->where('direction', TopicDirection::Subscribe->value)
            ->first();

        if (! $topic instanceof SchemaVersionTopic) {
            $this->log()->warning('Command node failed due to invalid target topic.', [
                'run_correlation_id' => $runCorrelationId,
                'node_id' => $nodeId,
                'target_topic_id' => $targetTopicId,
                'target_device_id' => $targetDeviceId,
            ]);

            return [
                'status' => 'failed',
                'output' => [],
                'error' => ['reason' => 'command_target_topic_invalid'],
            ];
        }

        $this->log()->info('Command node dispatching device command.', [
            'run_correlation_id' => $runCorrelationId,
            'node_id' => $nodeId,
            'target_device_id' => $device->id,
            'target_topic_id' => $topic->id,
            'payload_keys' => array_keys($resolvedPayload),
        ]);

        $commandLog = $this->deviceCommandDispatcher->dispatch(
            device: $device,
            topic: $topic,
            payload: $resolvedPayload,
            userId: null,
        );

        $commandStatus = $this->resolveCommandStatusValue($commandLog->status);

        if ($commandStatus === CommandStatus::Failed->value) {
            return [
                'status' => 'failed',
                'output' => [
                    'command_log_id' => $commandLog->id,
                    'command_status' => $commandStatus,
                ],
                'error' => [
                    'reason' => 'command_dispatch_failed',
                    'message' => $commandLog->error_message,
                ],
            ];
        }

        $this->log()->info('Command node dispatch completed.', [
            'run_correlation_id' => $runCorrelationId,
            'node_id' => $nodeId,
            'command_log_id' => $commandLog->id,
            'command_status' => $commandStatus,
        ]);

        return [
            'status' => 'completed',
            'output' => [
                'command_log_id' => $commandLog->id,
                'command_status' => $commandStatus,
                'target_device_id' => $device->id,
                'target_topic_id' => $topic->id,
            ],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $executionContext
     * @param  array<string, mixed>  $queryResult
     * @return array<string, mixed>
     */
    private function applyQueryResultToExecutionContext(array $executionContext, string $nodeId, array $queryResult): array
    {
        $queries = Arr::get($executionContext, 'queries');
        $resolvedQueries = is_array($queries) ? $queries : [];
        $resolvedQueries[$nodeId] = $queryResult;

        return [
            ...$executionContext,
            'query' => $queryResult,
            'queries' => $resolvedQueries,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function alertConfigHasRuntimeFields(array $config): bool
    {
        foreach (['channel', 'recipients', 'subject', 'body', 'cooldown'] as $runtimeKey) {
            if (array_key_exists($runtimeKey, $config)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAlertRecipients(mixed $recipients): array
    {
        if (! is_array($recipients)) {
            return [];
        }

        $resolved = [];

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }

            $email = strtolower(trim($recipient));

            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException("Invalid alert recipient [{$recipient}].");
            }

            $resolved[$email] = $email;
        }

        return array_values($resolved);
    }

    /**
     * @return array{value: int, unit: string}
     */
    private function resolveAlertCooldown(mixed $cooldown): array
    {
        if (! is_array($cooldown)) {
            return [
                'value' => self::ALERT_DEFAULT_COOLDOWN_VALUE,
                'unit' => self::ALERT_DEFAULT_COOLDOWN_UNIT,
            ];
        }

        $value = $this->resolvePositiveInt($cooldown['value'] ?? null);
        $unit = $cooldown['unit'] ?? null;

        if ($value === null || ! is_string($unit) || ! in_array($unit, ['minute', 'hour', 'day'], true)) {
            return [
                'value' => self::ALERT_DEFAULT_COOLDOWN_VALUE,
                'unit' => self::ALERT_DEFAULT_COOLDOWN_UNIT,
            ];
        }

        return [
            'value' => $value,
            'unit' => $unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $executionContext
     * @param  array{value: int, unit: string}  $cooldown
     * @return array<string, mixed>
     */
    private function buildAlertTemplateContext(
        AutomationRun $run,
        array $executionContext,
        string $nodeId,
        array $cooldown,
    ): array {
        $context = [
            'run' => [
                'id' => $run->id,
                'organization_id' => (int) $run->organization_id,
                'workflow_id' => (int) $run->workflow_id,
                'workflow_version_id' => (int) $run->workflow_version_id,
                'trigger_type' => $run->trigger_type,
            ],
            'trigger' => Arr::get($executionContext, 'trigger', []),
            'payload' => Arr::get($executionContext, 'payload', []),
            'query' => Arr::get($executionContext, 'query', []),
            'queries' => Arr::get($executionContext, 'queries', []),
            'alert' => [
                'node_id' => $nodeId,
                'cooldown' => $cooldown,
            ],
        ];

        $context['run_id'] = Arr::get($context, 'run.id');
        $context['workflow_id'] = Arr::get($context, 'run.workflow_id');
        $context['workflow_version_id'] = Arr::get($context, 'run.workflow_version_id');
        $context['trigger_value'] = Arr::get($context, 'trigger.value');
        $context['query_value'] = Arr::get($context, 'query.value');
        $context['window_start'] = Arr::get($context, 'query.window.start');
        $context['window_end'] = Arr::get($context, 'query.window.end');

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function interpolateTemplate(string $template, array $context): string
    {
        $resolvedTemplate = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            static function (array $matches) use ($context): string {
                $path = $matches[1];
                $value = data_get($context, $path);

                if (is_scalar($value) || $value instanceof \Stringable) {
                    return (string) $value;
                }

                if (is_array($value)) {
                    $encoded = json_encode($value);

                    return is_string($encoded) ? $encoded : '';
                }

                return '';
            },
            $template,
        );

        return is_string($resolvedTemplate) ? $resolvedTemplate : $template;
    }

    /**
     * @param  array<string, mixed>  $executionContext
     */
    private function buildAlertCooldownCacheKey(AutomationRun $run, array $executionContext, string $nodeId): string
    {
        $deviceId = Arr::get($executionContext, 'trigger.device_id');
        $topicId = Arr::get($executionContext, 'trigger.schema_version_topic_id');

        $resolvedDeviceId = is_int($deviceId) || is_string($deviceId) ? (string) $deviceId : 'none';
        $resolvedTopicId = is_int($topicId) || is_string($topicId) ? (string) $topicId : 'none';

        return implode(':', [
            'automation',
            'alert-cooldown',
            (string) $run->workflow_version_id,
            Str::slug($nodeId, '_'),
            $resolvedDeviceId,
            $resolvedTopicId,
        ]);
    }

    /**
     * @param  array{value: int, unit: string}  $cooldown
     */
    private function resolveAlertCooldownExpiration(array $cooldown): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return match ($cooldown['unit']) {
            'minute' => $now->addMinutes($cooldown['value']),
            'hour' => $now->addHours($cooldown['value']),
            'day' => $now->addDays($cooldown['value']),
            default => $now->addMinutes(self::ALERT_DEFAULT_COOLDOWN_VALUE),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTelemetryValue(array $payload, ParameterDefinition $parameter): mixed
    {
        $value = $parameter->extractValue($payload);

        if ($value !== null) {
            return $value;
        }

        return Arr::get($payload, $parameter->key);
    }

    private function resolveBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'off') {
                return false;
            }

            return true;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stepSummaries
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>|null  $error
     */
    private function recordStep(
        AutomationRun $run,
        array &$stepSummaries,
        string $nodeId,
        string $nodeType,
        string $status,
        array $input,
        array $output,
        ?array $error,
        float $startedAtMicrotime,
        string $runCorrelationId,
        int &$stepSequence,
    ): void {
        $stepSequence++;
        $durationMs = max(1, (int) round((microtime(true) - $startedAtMicrotime) * 1000));
        $startedAt = now()->subMilliseconds($durationMs);
        $finishedAt = now();
        $stepCorrelationId = $this->buildStepCorrelationId($runCorrelationId, $stepSequence, $nodeId);

        $run->steps()->create([
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'status' => $status,
            'input_snapshot' => $input,
            'output_snapshot' => $output,
            'error' => $error,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
        ]);

        $stepSummaries[] = [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'status' => $status,
            'step_correlation_id' => $stepCorrelationId,
        ];

        $this->log()->info('Automation workflow step recorded.', [
            'run_correlation_id' => $runCorrelationId,
            'step_correlation_id' => $stepCorrelationId,
            'automation_run_id' => $run->id,
            'workflow_version_id' => $run->workflow_version_id,
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'status' => $status,
            'duration_ms' => $durationMs,
            'input_keys' => array_keys($input),
            'output_keys' => array_keys($output),
            'error_reason' => $this->resolveErrorReason($error),
        ]);
    }

    private function buildStepCorrelationId(string $runCorrelationId, int $stepSequence, string $nodeId): string
    {
        $normalizedNodeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nodeId);
        $resolvedNodeId = is_string($normalizedNodeId) && $normalizedNodeId !== '' ? $normalizedNodeId : 'node';

        return "{$runCorrelationId}:{$stepSequence}:{$resolvedNodeId}";
    }

    private function log(): LoggerInterface
    {
        $configuredChannel = config('automation.log_channel', 'automation_pipeline');
        $logChannel = is_string($configuredChannel) && $configuredChannel !== ''
            ? $configuredChannel
            : 'automation_pipeline';

        return $this->logManager->channel($logChannel);
    }

    private function resolvePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $resolvedValue = (int) $value;

        return $resolvedValue > 0 ? $resolvedValue : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveGraphPayload(AutomationWorkflowVersion $workflowVersion): array
    {
        $payload = $workflowVersion->getAttribute('graph_json');

        return $this->resolveAssociativeArray($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAssociativeArray(mixed $value): array
    {
        if (is_array($value)) {
            $resolved = [];

            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $resolved[$key] = $item;
                }
            }

            return $resolved;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decodedValue = json_decode($value, true);

        if (! is_array($decodedValue)) {
            return [];
        }

        $resolved = [];

        foreach ($decodedValue as $key => $item) {
            if (is_string($key)) {
                $resolved[$key] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeStringKeyArray(array $payload): array
    {
        $resolved = [];

        foreach ($payload as $key => $item) {
            if (is_string($key)) {
                $resolved[$key] = $item;
            }
        }

        return $resolved;
    }

    private function resolveKeyAsString(mixed $value): ?string
    {
        if (is_int($value) || is_string($value)) {
            $resolved = (string) $value;

            return $resolved !== '' ? $resolved : null;
        }

        return null;
    }

    private function resolveCommandStatusValue(mixed $status): string
    {
        if ($status instanceof CommandStatus) {
            return $status->value;
        }

        return is_string($status) ? $status : CommandStatus::Failed->value;
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    private function resolveErrorReason(?array $error): ?string
    {
        if ($error === null) {
            return null;
        }

        $reason = Arr::get($error, 'reason');

        return is_string($reason) ? $reason : null;
    }
}
