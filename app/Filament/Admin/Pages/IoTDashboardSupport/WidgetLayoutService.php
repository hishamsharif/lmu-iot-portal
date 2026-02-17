<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\IoTDashboardSupport;

class WidgetLayoutService
{
    public const int GRID_COLUMNS = 24;

    public const int GRID_CELL_HEIGHT = 96;

    /**
     * @param  array<string, mixed>  $data
     * @param  array{x: int, y: int, w: int, h: int, columns?: int, card_height_px?: int}|null  $defaultLayout
     * @return array{x: int, y: int, w: int, h: int, columns: int, card_height_px: int}
     */
    public function buildLayout(array $data, ?array $defaultLayout = null): array
    {
        $fallback = $defaultLayout ?? [
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
        ];

        $width = $this->normalizeInt($data['grid_columns'] ?? $fallback['w'], $fallback['w'], 1, self::GRID_COLUMNS);
        $height = $this->gridRowsFromCardHeight(
            $data['card_height_px'] ?? ($fallback['h'] * self::GRID_CELL_HEIGHT),
        );

        return [
            'x' => max($this->toInt($fallback['x'], 0), 0),
            'y' => max($this->toInt($fallback['y'], 0), 0),
            'w' => $width,
            'h' => $height,
            'columns' => self::GRID_COLUMNS,
            'card_height_px' => $height * self::GRID_CELL_HEIGHT,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public function gridColumnOptions(): array
    {
        $options = [];

        foreach (range(1, self::GRID_COLUMNS) as $column) {
            $suffix = $column === 1 ? 'column' : 'columns';
            $options[(string) $column] = "{$column} {$suffix}";
        }

        return $options;
    }

    public function gridRowsFromCardHeight(mixed $cardHeight): int
    {
        $heightPx = $this->normalizeInt($cardHeight, 360, 260, 900);

        return $this->normalizeInt((int) round($heightPx / self::GRID_CELL_HEIGHT), 4, 2, 12);
    }

    private function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function normalizeInt(mixed $value, int $default, int $min, int $max): int
    {
        return min(max($this->toInt($value, $default), $min), $max);
    }
}
