<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\Concerns;

trait NormalizesWidgetConfig
{
    /**
     * @param  array<int, mixed>|mixed  $series
     * @return array<int, array{key: string, label: string, color: string}>
     */
    private static function normalizeSeries(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach (array_values($series) as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = is_string($entry['key'] ?? null)
                ? trim((string) $entry['key'])
                : '';

            if ($key === '' || in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;
            $normalized[] = [
                'key' => $key,
                'label' => is_string($entry['label'] ?? null) && trim((string) $entry['label']) !== ''
                    ? (string) $entry['label']
                    : $key,
                'color' => is_string($entry['color'] ?? null) && trim((string) $entry['color']) !== ''
                    ? (string) $entry['color']
                    : self::seriesPalette()[$index % count(self::seriesPalette())],
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private static function seriesPalette(): array
    {
        return [
            '#22d3ee',
            '#a855f7',
            '#f97316',
            '#10b981',
            '#f43f5e',
            '#3b82f6',
            '#f59e0b',
            '#14b8a6',
        ];
    }

    private static function normalizeBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private static function normalizeInt(mixed $value, int $default, int $minimum, int $maximum): int
    {
        $resolved = is_numeric($value)
            ? (int) round((float) $value)
            : $default;

        return min(max($resolved, $minimum), $maximum);
    }

    private static function normalizeFloat(mixed $value, float $default): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }
}
