<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Services;

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Models\DeviceDesiredTopicState;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Domain\DeviceSchema\Enums\TopicLinkType;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\CommandCompleted;
use App\Events\DeviceStateReceived;
use Illuminate\Support\Carbon;

class DeviceFeedbackReconciler
{
    private const int REGISTRY_TTL_SECONDS = 30;

    /**
     * @var array<string, array{device: Device, topic: SchemaVersionTopic}>
     */
    private array $topicRegistry = [];

    private ?Carbon $lastRegistryRefreshAt = null;

    public function __construct(
        private readonly NatsDeviceStateStore $stateStore,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     device_uuid: string,
     *     device_external_id: string|null,
     *     schema_version_topic_id: int,
     *     topic: string,
     *     purpose: string,
     *     command_log_id: int|null
     * }|null
     */
    public function reconcileInboundMessage(
        string $mqttTopic,
        array $payload,
        string $host = '127.0.0.1',
        int $port = 4223,
    ): ?array {
        $resolved = $this->resolveRegistryTopic($mqttTopic);

        if ($resolved === null) {
            return null;
        }

        $device = $resolved['device'];
        $topic = $resolved['topic'];

        $this->stateStore->store($device->uuid, $mqttTopic, $payload, $host, $port);

        $matchedCommandLog = $this->matchCommandLog($device, $topic, $payload);

        if ($matchedCommandLog instanceof DeviceCommandLog) {
            $this->applyFeedbackToCommandLog($matchedCommandLog, $topic, $payload);
        }

        event(new DeviceStateReceived(
            topic: $mqttTopic,
            deviceUuid: $device->uuid,
            deviceExternalId: $device->external_id,
            payload: $payload,
            commandLogId: $matchedCommandLog?->id,
        ));

        return [
            'device_uuid' => $device->uuid,
            'device_external_id' => $device->external_id,
            'schema_version_topic_id' => (int) $topic->id,
            'topic' => $mqttTopic,
            'purpose' => $topic->resolvedPurpose()->value,
            'command_log_id' => $matchedCommandLog?->id,
        ];
    }

    public function refreshRegistry(): void
    {
        $this->topicRegistry = [];

        $devices = Device::query()
            ->with([
                'deviceType',
                'schemaVersion.topics.outgoingLinks',
            ])
            ->whereNotNull('device_schema_version_id')
            ->get();

        foreach ($devices as $device) {
            $topics = $device->schemaVersion?->topics;

            if ($topics === null) {
                continue;
            }

            foreach ($topics as $topic) {
                if (! $topic->isPublish()) {
                    continue;
                }

                $this->topicRegistry[$topic->resolvedTopic($device)] = [
                    'device' => $device,
                    'topic' => $topic,
                ];
            }
        }

        $this->lastRegistryRefreshAt = now();
    }

    /**
     * @return array{device: Device, topic: SchemaVersionTopic}|null
     */
    private function resolveRegistryTopic(string $mqttTopic): ?array
    {
        if (
            $this->lastRegistryRefreshAt === null
            || $this->lastRegistryRefreshAt->diffInSeconds(now()) > self::REGISTRY_TTL_SECONDS
        ) {
            $this->refreshRegistry();
        }

        return $this->topicRegistry[$mqttTopic] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function matchCommandLog(Device $device, SchemaVersionTopic $incomingTopic, array $payload): ?DeviceCommandLog
    {
        $correlationId = $this->extractCorrelationId($payload);

        if ($correlationId !== null) {
            $matched = DeviceCommandLog::query()
                ->where('device_id', $device->id)
                ->where('correlation_id', $correlationId)
                ->whereIn('status', [
                    CommandStatus::Pending->value,
                    CommandStatus::Sent->value,
                    CommandStatus::Acknowledged->value,
                ])
                ->latest('id')
                ->first();

            if ($matched !== null) {
                return $matched;
            }
        }

        $candidateTopicIds = $this->resolveCandidateCommandTopicIds($device, $incomingTopic);

        $candidates = DeviceCommandLog::query()
            ->when(
                $candidateTopicIds !== [],
                fn ($query) => $query->whereIn('schema_version_topic_id', $candidateTopicIds)
            )
            ->where('device_id', $device->id)
            ->whereIn('status', [
                CommandStatus::Pending->value,
                CommandStatus::Sent->value,
                CommandStatus::Acknowledged->value,
            ])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->limit(25)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $feedbackComparable = $this->flattenPayload($this->withoutMeta($payload));
        $bestMatch = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $commandComparable = $this->flattenPayload(
                $this->withoutMeta($this->normalizePayload($candidate->command_payload))
            );
            $score = $this->payloadOverlapScore($commandComparable, $feedbackComparable);

            if ($score > $bestScore) {
                $bestMatch = $candidate;
                $bestScore = $score;
            }
        }

        return $bestMatch ?? $candidates->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyFeedbackToCommandLog(DeviceCommandLog $commandLog, SchemaVersionTopic $incomingTopic, array $payload): void
    {
        $now = now();

        $updates = [
            'response_payload' => $payload,
            'response_schema_version_topic_id' => $incomingTopic->id,
        ];

        if ($incomingTopic->isPurposeAck()) {
            $updates['status'] = CommandStatus::Acknowledged;
            $updates['acknowledged_at'] = $commandLog->acknowledged_at ?? $now;

            if ($this->shouldCompleteOnAck($commandLog)) {
                $updates['status'] = CommandStatus::Completed;
                $updates['completed_at'] = $now;
            }
        } else {
            $updates['status'] = CommandStatus::Completed;
            $updates['acknowledged_at'] = $commandLog->acknowledged_at ?? $now;
            $updates['completed_at'] = $now;
        }

        $commandLog->update($updates);
        $commandLog->refresh();
        $commandLog->loadMissing('device');

        $isCompleted = $this->isCompletedStatus($commandLog->status);

        if ($isCompleted) {
            event(new CommandCompleted($commandLog));

            $desiredQuery = DeviceDesiredTopicState::query()
                ->where('device_id', $commandLog->device_id)
                ->where('schema_version_topic_id', $commandLog->schema_version_topic_id);

            if (is_string($commandLog->correlation_id) && $commandLog->correlation_id !== '') {
                $desiredQuery->where('correlation_id', $commandLog->correlation_id);
            }

            $desiredQuery->update([
                'reconciled_at' => $now,
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function resolveCandidateCommandTopicIds(Device $device, SchemaVersionTopic $incomingTopic): array
    {
        $linkType = match (true) {
            $incomingTopic->isPurposeState() => TopicLinkType::StateFeedback->value,
            $incomingTopic->isPurposeAck() => TopicLinkType::AckFeedback->value,
            default => null,
        };

        $linkedTopicIds = $incomingTopic->incomingLinks()
            ->when($linkType !== null, fn ($query) => $query->where('link_type', $linkType))
            ->pluck('from_schema_version_topic_id')
            ->all();

        $normalizedLinkedTopicIds = [];

        foreach ($linkedTopicIds as $linkedTopicId) {
            if (! is_numeric($linkedTopicId)) {
                continue;
            }

            $normalizedLinkedTopicIds[] = (int) $linkedTopicId;
        }

        $normalizedLinkedTopicIds = array_values(array_unique($normalizedLinkedTopicIds));

        if ($normalizedLinkedTopicIds !== []) {
            return $normalizedLinkedTopicIds;
        }

        $topics = $device->schemaVersion?->topics;

        if ($topics === null) {
            return [];
        }

        $topicIds = [];

        foreach ($topics as $topic) {
            if (! $topic->isPurposeCommand()) {
                continue;
            }

            $topicIds[] = (int) $topic->id;
        }

        return array_values(array_unique($topicIds));
    }

    private function shouldCompleteOnAck(DeviceCommandLog $commandLog): bool
    {
        $commandLog->loadMissing('topic.stateFeedbackTopics');
        $commandTopic = $commandLog->topic;

        if (! $commandTopic instanceof SchemaVersionTopic) {
            return true;
        }

        return $commandTopic->stateFeedbackTopics->isEmpty();
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function extractCorrelationId(array $payload): ?string
    {
        $correlationId = data_get($payload, '_meta.command_id');

        if (! is_string($correlationId) || trim($correlationId) === '') {
            return null;
        }

        return trim($correlationId);
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    private function withoutMeta(array $payload): array
    {
        unset($payload['_meta']);

        return $payload;
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<string, string>
     */
    private function flattenPayload(array $payload, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($payload as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flattened += $this->flattenPayload($value, $fullKey);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flattened[$fullKey] = (string) $value;
            }
        }

        return $flattened;
    }

    /**
     * @param  array<string, string>  $left
     * @param  array<string, string>  $right
     */
    private function payloadOverlapScore(array $left, array $right): int
    {
        $score = 0;

        foreach ($left as $key => $value) {
            if (array_key_exists($key, $right) && $right[$key] === $value) {
                $score++;
            }
        }

        return $score;
    }

    private function isCompletedStatus(mixed $status): bool
    {
        if ($status instanceof CommandStatus) {
            return $status === CommandStatus::Completed;
        }

        return $status === CommandStatus::Completed->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
