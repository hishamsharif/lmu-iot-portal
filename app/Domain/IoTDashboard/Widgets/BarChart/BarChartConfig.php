<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\BarChart;

use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Widgets\Concerns\NormalizesWidgetConfig;

final class BarChartConfig implements WidgetConfig
{
    use NormalizesWidgetConfig;

    /**
     * @param  array<int, array{key: string, label: string, color: string}>  $series
     */
    private function __construct(
        private readonly array $series,
        private readonly bool $useWebsocket,
        private readonly bool $usePolling,
        private readonly int $pollingIntervalSeconds,
        private readonly int $lookbackMinutes,
        private readonly int $maxPoints,
        private readonly BarInterval $barInterval,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): static
    {
        $transport = is_array($config['transport'] ?? null)
            ? $config['transport']
            : [];
        $window = is_array($config['window'] ?? null)
            ? $config['window']
            : [];

        $intervalValue = $config['bar_interval'] ?? data_get($config, 'meta.bar_interval') ?? BarInterval::Hourly->value;
        $barInterval = is_string($intervalValue)
            ? BarInterval::tryFrom(strtolower(trim($intervalValue)))
            : null;

        return new self(
            series: self::normalizeSeries($config['series'] ?? []),
            useWebsocket: self::normalizeBool(
                $transport['use_websocket'] ?? $config['use_websocket'] ?? false,
                false,
            ),
            usePolling: self::normalizeBool(
                $transport['use_polling'] ?? $config['use_polling'] ?? true,
                true,
            ),
            pollingIntervalSeconds: self::normalizeInt(
                $transport['polling_interval_seconds'] ?? $config['polling_interval_seconds'] ?? 60,
                60,
                2,
                300,
            ),
            lookbackMinutes: self::normalizeInt(
                $window['lookback_minutes'] ?? $config['lookback_minutes'] ?? 43200,
                43200,
                1,
                129600,
            ),
            maxPoints: self::normalizeInt(
                $window['max_points'] ?? $config['max_points'] ?? 31,
                31,
                1,
                1000,
            ),
            barInterval: $barInterval ?? BarInterval::Hourly,
        );
    }

    public function type(): WidgetType
    {
        return WidgetType::BarChart;
    }

    public function series(): array
    {
        return $this->series;
    }

    public function useWebsocket(): bool
    {
        return $this->useWebsocket;
    }

    public function usePolling(): bool
    {
        return $this->usePolling;
    }

    public function pollingIntervalSeconds(): int
    {
        return $this->pollingIntervalSeconds;
    }

    public function lookbackMinutes(): int
    {
        return $this->lookbackMinutes;
    }

    public function maxPoints(): int
    {
        return $this->maxPoints;
    }

    public function barInterval(): BarInterval
    {
        return $this->barInterval;
    }

    public function toArray(): array
    {
        return [
            'series' => $this->series,
            'transport' => [
                'use_websocket' => $this->useWebsocket,
                'use_polling' => $this->usePolling,
                'polling_interval_seconds' => $this->pollingIntervalSeconds,
            ],
            'window' => [
                'lookback_minutes' => $this->lookbackMinutes,
                'max_points' => $this->maxPoints,
            ],
            'bar_interval' => $this->barInterval->value,
        ];
    }

    public function meta(): array
    {
        return [
            'bar_interval' => $this->barInterval->value,
        ];
    }
}
