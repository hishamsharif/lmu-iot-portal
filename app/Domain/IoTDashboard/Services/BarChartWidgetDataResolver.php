<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Services;

use App\Domain\IoTDashboard\Enums\BarInterval;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BarChartWidgetDataResolver
{
    /**
     * @return array{
     *     widget_id: int,
     *     topic_id: int,
     *     generated_at: string,
     *     interval: string,
     *     series: array<int, array{
     *         key: string,
     *         label: string,
     *         color: string,
     *         points: array<int, array{timestamp: string, value: int|float}>
     *     }>
     * }
     */
    public function resolve(
        IoTDashboardWidget $widget,
        int $lookbackMinutes,
        int $maxPoints,
        BarInterval $interval,
    ): array {
        $deviceId = is_numeric($widget->device_id) ? (int) $widget->device_id : null;
        $seriesConfiguration = $widget->resolvedSeriesConfig();
        $logs = $deviceId === null
            ? collect()
            : $this->fetchTelemetryLogs(
                schemaVersionTopicId: (int) $widget->schema_version_topic_id,
                deviceId: $deviceId,
                lookbackMinutes: $lookbackMinutes,
            );

        $series = [];

        foreach ($seriesConfiguration as $config) {
            $series[] = [
                'key' => $config['key'],
                'label' => $config['label'],
                'color' => $config['color'],
                'points' => $this->buildConsumptionBuckets(
                    logs: $logs,
                    parameterKey: $config['key'],
                    interval: $interval,
                    maxPoints: $maxPoints,
                ),
            ];
        }

        return [
            'widget_id' => (int) $widget->id,
            'topic_id' => (int) $widget->schema_version_topic_id,
            'device_id' => $deviceId,
            'generated_at' => now()->toIso8601String(),
            'interval' => $interval->value,
            'series' => $series,
        ];
    }

    /**
     * @return Collection<int, DeviceTelemetryLog>
     */
    private function fetchTelemetryLogs(
        int $schemaVersionTopicId,
        ?int $deviceId,
        int $lookbackMinutes,
    ): Collection {
        return DeviceTelemetryLog::query()
            ->where('schema_version_topic_id', $schemaVersionTopicId)
            ->when(
                is_numeric($deviceId) && $deviceId > 0,
                fn (Builder $query): Builder => $query->where('device_id', $deviceId),
            )
            ->where('recorded_at', '>=', now()->subMinutes($lookbackMinutes))
            ->orderBy('recorded_at')
            ->get(['id', 'recorded_at', 'transformed_values']);
    }

    /**
     * @param  Collection<int, DeviceTelemetryLog>  $logs
     * @return array<int, array{timestamp: string, value: int|float}>
     */
    private function buildConsumptionBuckets(
        Collection $logs,
        string $parameterKey,
        BarInterval $interval,
        int $maxPoints,
    ): array {
        /** @var array<string, array{timestamp: string, start: float, end: float}> $buckets */
        $buckets = [];

        foreach ($logs as $log) {
            $value = $this->extractNumericValue($log->transformed_values, $parameterKey);

            if ($value === null) {
                continue;
            }

            $recordedAt = $this->normalizeRecordedAt($log->recorded_at);

            if (! $recordedAt instanceof Carbon) {
                continue;
            }

            $bucketStart = $this->resolveBucketStart($recordedAt, $interval)->toIso8601String();

            if (! array_key_exists($bucketStart, $buckets)) {
                $buckets[$bucketStart] = [
                    'timestamp' => $bucketStart,
                    'start' => $value,
                    'end' => $value,
                ];

                continue;
            }

            $buckets[$bucketStart]['end'] = $value;
        }

        if ($buckets === []) {
            return [];
        }

        ksort($buckets);

        $points = array_map(
            static fn (array $bucket): array => [
                'timestamp' => $bucket['timestamp'],
                'value' => round(max($bucket['end'] - $bucket['start'], 0), 3),
            ],
            array_values($buckets),
        );

        if ($maxPoints > 0 && count($points) > $maxPoints) {
            $points = array_slice($points, -$maxPoints);
        }

        return $points;
    }

    private function normalizeRecordedAt(mixed $recordedAt): ?Carbon
    {
        if ($recordedAt instanceof Carbon) {
            return $recordedAt;
        }

        if (is_string($recordedAt) && trim($recordedAt) !== '') {
            return Carbon::parse($recordedAt);
        }

        return null;
    }

    private function resolveBucketStart(Carbon $recordedAt, BarInterval $interval): Carbon
    {
        $bucketStart = $recordedAt->copy();

        return match ($interval) {
            BarInterval::Hourly => $bucketStart->startOfHour(),
            BarInterval::Daily => $bucketStart->startOfDay(),
        };
    }

    private function extractNumericValue(mixed $values, string $parameterKey): ?float
    {
        if (! is_array($values)) {
            return null;
        }

        $value = data_get($values, $parameterKey);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
