<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\GaugeChart;

use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Widgets\Concerns\NormalizesWidgetConfig;

final class GaugeChartConfig implements WidgetConfig
{
    use NormalizesWidgetConfig;

    /**
     * @param  array<int, array{key: string, label: string, color: string}>  $series
     * @param  array<int, array{from: int|float, to: int|float, color: string}>  $gaugeRanges
     */
    private function __construct(
        private readonly array $series,
        private readonly bool $useWebsocket,
        private readonly bool $usePolling,
        private readonly int $pollingIntervalSeconds,
        private readonly int $lookbackMinutes,
        private readonly int $maxPoints,
        private readonly GaugeStyle $gaugeStyle,
        private readonly float $gaugeMinimum,
        private readonly float $gaugeMaximum,
        private readonly array $gaugeRanges,
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

        $styleValue = is_string($config['gauge_style'] ?? null)
            ? strtolower(trim((string) $config['gauge_style']))
            : GaugeStyle::Classic->value;
        $style = GaugeStyle::tryFrom($styleValue) ?? GaugeStyle::Classic;

        $minimum = self::normalizeFloat($config['gauge_min'] ?? 0, 0);
        $maximum = self::normalizeFloat($config['gauge_max'] ?? 100, 100);

        if ($maximum <= $minimum) {
            $maximum = $minimum + 1;
        }

        return new self(
            series: self::normalizeSeries($config['series'] ?? []),
            useWebsocket: self::normalizeBool(
                $transport['use_websocket'] ?? $config['use_websocket'] ?? true,
                true,
            ),
            usePolling: self::normalizeBool(
                $transport['use_polling'] ?? $config['use_polling'] ?? true,
                true,
            ),
            pollingIntervalSeconds: self::normalizeInt(
                $transport['polling_interval_seconds'] ?? $config['polling_interval_seconds'] ?? 10,
                10,
                2,
                300,
            ),
            lookbackMinutes: self::normalizeInt(
                $window['lookback_minutes'] ?? $config['lookback_minutes'] ?? 180,
                180,
                1,
                129600,
            ),
            maxPoints: self::normalizeInt(
                $window['max_points'] ?? $config['max_points'] ?? 1,
                1,
                1,
                100,
            ),
            gaugeStyle: $style,
            gaugeMinimum: $minimum,
            gaugeMaximum: $maximum,
            gaugeRanges: self::normalizeRanges(
                $config['gauge_ranges'] ?? null,
                $minimum,
                $maximum,
            ),
        );
    }

    public function type(): WidgetType
    {
        return WidgetType::GaugeChart;
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

    public function gaugeStyle(): GaugeStyle
    {
        return $this->gaugeStyle;
    }

    public function gaugeMinimum(): float
    {
        return $this->gaugeMinimum;
    }

    public function gaugeMaximum(): float
    {
        return $this->gaugeMaximum;
    }

    /**
     * @return array<int, array{from: int|float, to: int|float, color: string}>
     */
    public function gaugeRanges(): array
    {
        return $this->gaugeRanges;
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
            'gauge_style' => $this->gaugeStyle->value,
            'gauge_min' => $this->gaugeMinimum,
            'gauge_max' => $this->gaugeMaximum,
            'gauge_ranges' => $this->gaugeRanges,
        ];
    }

    public function meta(): array
    {
        return [
            'gauge_style' => $this->gaugeStyle->value,
            'gauge_min' => $this->gaugeMinimum,
            'gauge_max' => $this->gaugeMaximum,
            'gauge_ranges' => $this->gaugeRanges,
        ];
    }

    /**
     * @param  array<int, mixed>|mixed  $ranges
     * @return array<int, array{from: int|float, to: int|float, color: string}>
     */
    private static function normalizeRanges(mixed $ranges, float $minimum, float $maximum): array
    {
        if (! is_array($ranges)) {
            return self::defaultRanges();
        }

        $resolved = [];

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $from = is_numeric($range['from'] ?? null)
                ? (float) $range['from']
                : null;
            $to = is_numeric($range['to'] ?? null)
                ? (float) $range['to']
                : null;
            $color = is_string($range['color'] ?? null)
                ? trim((string) $range['color'])
                : '';

            if ($from === null || $to === null || $color === '' || $to <= $from) {
                continue;
            }

            $resolved[] = [
                'from' => min(max($from, $minimum), $maximum),
                'to' => min(max($to, $minimum), $maximum),
                'color' => $color,
            ];
        }

        if ($resolved === []) {
            return self::defaultRanges();
        }

        usort($resolved, fn (array $left, array $right): int => $left['from'] <=> $right['from']);

        return $resolved;
    }

    /**
     * @return array<int, array{from: int|float, to: int|float, color: string}>
     */
    private static function defaultRanges(): array
    {
        return [
            ['from' => 0, 'to' => 50, 'color' => '#10b981'],
            ['from' => 50, 'to' => 80, 'color' => '#f59e0b'],
            ['from' => 80, 'to' => 100, 'color' => '#ef4444'],
        ];
    }
}
