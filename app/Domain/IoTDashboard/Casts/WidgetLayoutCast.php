<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<array{x: int, y: int, w: int, h: int, columns: int, card_height_px: int}, mixed>
 */
class WidgetLayoutCast implements CastsAttributes
{
    private const int GRID_COLUMNS = 24;

    private const int GRID_CELL_HEIGHT = 96;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{x: int, y: int, w: int, h: int, columns: int, card_height_px: int}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $layout = is_array($value)
            ? $value
            : json_decode(
                is_string($value) ? $value : '{}',
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

        return $this->normalizeLayout(is_array($layout) ? $layout : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $layout = is_array($value) ? $value : [];

        return [
            $key => json_encode(
                $this->normalizeLayout($layout),
                JSON_THROW_ON_ERROR,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{x: int, y: int, w: int, h: int, columns: int, card_height_px: int}
     */
    private function normalizeLayout(array $layout): array
    {
        $x = $this->normalizeInt($layout['x'] ?? 0, 0, 0, 9999);
        $y = $this->normalizeInt($layout['y'] ?? 0, 0, 0, 9999);
        $w = $this->normalizeInt($layout['w'] ?? 6, 6, 1, self::GRID_COLUMNS);
        $h = $this->normalizeInt($layout['h'] ?? 4, 4, 2, 12);

        return [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'columns' => self::GRID_COLUMNS,
            'card_height_px' => $h * self::GRID_CELL_HEIGHT,
        ];
    }

    private function normalizeInt(mixed $value, int $default, int $minimum, int $maximum): int
    {
        $resolved = is_numeric($value)
            ? (int) round((float) $value)
            : $default;

        return min(max($resolved, $minimum), $maximum);
    }
}
