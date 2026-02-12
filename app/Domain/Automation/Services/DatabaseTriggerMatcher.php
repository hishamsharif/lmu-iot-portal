<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Contracts\TriggerMatcher;
use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\DeviceSchema\Services\JsonLogicEvaluator;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Log\LogManager;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class DatabaseTriggerMatcher implements TriggerMatcher
{
    public function __construct(
        private readonly JsonLogicEvaluator $jsonLogicEvaluator,
        private readonly LogManager $logManager,
    ) {}

    public function matchTelemetryTriggers(DeviceTelemetryLog $telemetryLog): Collection
    {
        $telemetryLogId = $this->resolveKeyAsString($telemetryLog->getKey());
        $device = $telemetryLog->device;
        $baseLogContext = [
            'telemetry_log_id' => $telemetryLogId,
            'device_id' => $telemetryLog->device_id,
            'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
        ];

        if ($device === null) {
            $this->log()->warning('Automation trigger matcher skipped telemetry because device was missing.', $baseLogContext);

            return collect();
        }

        $candidates = AutomationTelemetryTrigger::query()
            ->where('organization_id', $device->organization_id)
            ->where(function ($query) use ($device): void {
                $query->whereNull('device_id')
                    ->orWhere('device_id', $device->id);
            })
            ->where(function ($query) use ($device): void {
                $query->whereNull('device_type_id')
                    ->orWhere('device_type_id', $device->device_type_id);
            })
            ->where(function ($query) use ($telemetryLog): void {
                $query->whereNull('schema_version_topic_id')
                    ->orWhere('schema_version_topic_id', $telemetryLog->schema_version_topic_id);
            })
            ->get();

        $matchedWorkflowVersionIds = $candidates
            ->filter(function (AutomationTelemetryTrigger $trigger) use ($telemetryLog): bool {
                $expression = $trigger->getAttribute('filter_expression');
                if (! is_array($expression) || $expression === []) {
                    return true;
                }

                $payload = $telemetryLog->getAttribute('transformed_values');

                if (! is_array($payload)) {
                    return false;
                }

                $evaluationResult = $this->jsonLogicEvaluator->evaluate(
                    $expression,
                    $this->normalizeStringKeyArray($payload),
                );

                return (bool) $evaluationResult;
            })
            ->pluck('workflow_version_id')
            ->map(static function (mixed $id): ?int {
                if (! is_numeric($id)) {
                    return null;
                }

                return (int) $id;
            })
            ->filter(static fn (?int $id): bool => $id !== null)
            ->unique()
            ->values();

        $this->log()->debug('Automation trigger matcher evaluated telemetry triggers.', [
            ...$baseLogContext,
            'organization_id' => $device->organization_id,
            'candidate_count' => $candidates->count(),
            'matched_count' => $matchedWorkflowVersionIds->count(),
            'matched_workflow_version_ids' => $matchedWorkflowVersionIds->all(),
        ]);

        return $matchedWorkflowVersionIds;
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

    /**
     * @param  array<mixed, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeStringKeyArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
