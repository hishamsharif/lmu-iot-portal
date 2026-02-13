<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Services;

use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LineChartWidgetDataResolver
{
    /**
     * @return array{
     *     widget_id: int,
     *     topic_id: int,
     *     generated_at: string,
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
    ): array {
        $deviceId = is_numeric($widget->device_id) ? (int) $widget->device_id : null;
        $seriesConfiguration = $widget->resolvedSeriesConfig();
        $logs = $deviceId === null
            ? collect()
            : $this->fetchTelemetryLogs(
                schemaVersionTopicId: (int) $widget->schema_version_topic_id,
                deviceId: $deviceId,
                lookbackMinutes: $lookbackMinutes,
                maxPoints: $maxPoints,
            );

        $series = [];

        foreach ($seriesConfiguration as $config) {
            $points = [];
            $key = $config['key'];

            foreach ($logs as $log) {
                $value = $this->extractNumericValue($log->transformed_values, $key);

                if ($value === null) {
                    continue;
                }

                $timestamp = $log->recorded_at?->toIso8601String();

                if (! is_string($timestamp)) {
                    continue;
                }

                $points[] = [
                    'timestamp' => $timestamp,
                    'value' => $value,
                ];
            }

            $series[] = [
                'key' => $config['key'],
                'label' => $config['label'],
                'color' => $config['color'],
                'points' => $points,
            ];
        }

        return [
            'widget_id' => (int) $widget->id,
            'topic_id' => (int) $widget->schema_version_topic_id,
            'device_id' => $deviceId,
            'generated_at' => now()->toIso8601String(),
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
        int $maxPoints,
    ): Collection {
        return DeviceTelemetryLog::query()
            ->where('schema_version_topic_id', $schemaVersionTopicId)
            ->when(
                is_numeric($deviceId) && $deviceId > 0,
                fn (Builder $query): Builder => $query->where('device_id', $deviceId),
            )
            ->where('recorded_at', '>=', now()->subMinutes($lookbackMinutes))
            ->orderByDesc('recorded_at')
            ->limit($maxPoints)
            ->get(['id', 'recorded_at', 'transformed_values'])
            ->sortBy('recorded_at')
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    private function extractNumericValue(?array $values, string $parameterKey): int|float|null
    {
        if (! is_array($values)) {
            return null;
        }

        $value = data_get($values, $parameterKey);

        if (is_numeric($value)) {
            return $value + 0;
        }

        return null;
    }
}
