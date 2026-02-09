<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Support\Carbon;

class DeviceTelemetryTopicResolver
{
    /**
     * @var array<string, array{device: Device, topic: SchemaVersionTopic}>
     */
    private array $topicRegistry = [];

    private ?Carbon $lastRegistryRefreshAt = null;

    /**
     * @return array{device: Device, topic: SchemaVersionTopic}|null
     */
    public function resolve(string $mqttTopic): ?array
    {
        if ($this->shouldRefreshRegistry()) {
            $this->refreshRegistry();
        }

        return $this->topicRegistry[$mqttTopic] ?? null;
    }

    public function refreshRegistry(): void
    {
        $this->topicRegistry = [];

        $devices = Device::query()
            ->with([
                'deviceType',
                'schemaVersion.topics',
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

    private function shouldRefreshRegistry(): bool
    {
        if (! $this->lastRegistryRefreshAt instanceof Carbon) {
            return true;
        }

        $ttl = config('ingestion.registry_ttl_seconds', 30);
        $ttlSeconds = is_numeric($ttl) ? (int) $ttl : 30;

        return $this->lastRegistryRefreshAt->diffInSeconds(now()) > $ttlSeconds;
    }
}
