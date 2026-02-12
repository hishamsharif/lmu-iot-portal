<?php

declare(strict_types=1);

namespace App\Domain\Automation\Listeners;

use App\Domain\Automation\Contracts\TriggerMatcher;
use App\Domain\Automation\Jobs\StartAutomationRunFromTelemetry;
use App\Events\TelemetryReceived;
use Illuminate\Log\LogManager;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class QueueTelemetryAutomationRuns
{
    public function __construct(
        private readonly TriggerMatcher $triggerMatcher,
        private readonly LogManager $logManager,
    ) {}

    public function handle(TelemetryReceived $event): void
    {
        if (! (bool) config('automation.enabled', true)) {
            $this->log()->debug('Automation telemetry listener skipped because pipeline is disabled.', [
                'telemetry_log_id' => $this->resolveKeyAsString($event->telemetryLog->getKey()),
            ]);

            return;
        }

        $eventCorrelationId = (string) Str::uuid();
        $telemetryLog = $event->telemetryLog;
        $workflowVersionIds = $this->triggerMatcher->matchTelemetryTriggers($event->telemetryLog);
        $telemetryLogId = $this->resolveKeyAsString($telemetryLog->getKey());

        if ($telemetryLogId === null) {
            $this->log()->warning('Automation telemetry event skipped because telemetry id could not be resolved.', [
                'device_id' => $telemetryLog->device_id,
                'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
            ]);

            return;
        }

        $logContext = [
            'event_correlation_id' => $eventCorrelationId,
            'telemetry_log_id' => $telemetryLogId,
            'device_id' => $telemetryLog->device_id,
            'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
            'matched_workflow_version_ids' => $workflowVersionIds->values()->all(),
            'match_count' => $workflowVersionIds->count(),
            'queue_connection' => config('automation.queue_connection', config('queue.default', 'database')),
            'queue' => config('automation.queue', 'default'),
        ];

        if ($workflowVersionIds->isEmpty()) {
            $this->log()->debug('Automation telemetry event produced no matching workflows.', $logContext);

            return;
        }

        $this->log()->info('Automation telemetry event matched workflows.', $logContext);

        foreach ($workflowVersionIds as $workflowVersionId) {
            $resolvedWorkflowVersionId = (int) $workflowVersionId;

            $this->log()->debug('Queueing automation run from telemetry event.', [
                ...$logContext,
                'workflow_version_id' => $resolvedWorkflowVersionId,
            ]);

            StartAutomationRunFromTelemetry::dispatch(
                workflowVersionId: $resolvedWorkflowVersionId,
                telemetryLogId: $telemetryLogId,
                eventCorrelationId: $eventCorrelationId,
            );
        }
    }

    private function log(): LoggerInterface
    {
        $configuredChannel = config('automation.log_channel', 'automation_pipeline');
        $logChannel = is_string($configuredChannel) && $configuredChannel !== ''
            ? $configuredChannel
            : 'automation_pipeline';

        return $this->logManager->channel($logChannel);
    }

    private function resolveKeyAsString(mixed $value): ?string
    {
        if (is_int($value) || is_string($value)) {
            $resolved = (string) $value;

            return $resolved !== '' ? $resolved : null;
        }

        return null;
    }
}
