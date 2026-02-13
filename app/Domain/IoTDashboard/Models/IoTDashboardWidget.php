<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Models;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Database\Factories\Domain\IoTDashboard\Models\IoTDashboardWidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IoTDashboardWidget extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\IoTDashboard\Models\IoTDashboardWidgetFactory> */
    use HasFactory;

    protected $table = 'iot_dashboard_widgets';

    protected $guarded = ['id'];

    protected static function newFactory(): IoTDashboardWidgetFactory
    {
        return IoTDashboardWidgetFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'series_config' => 'array',
            'options' => 'array',
            'use_websocket' => 'bool',
            'use_polling' => 'bool',
            'polling_interval_seconds' => 'integer',
            'lookback_minutes' => 'integer',
            'max_points' => 'integer',
            'sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<IoTDashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(IoTDashboard::class, 'iot_dashboard_id');
    }

    /**
     * @return BelongsTo<SchemaVersionTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(SchemaVersionTopic::class, 'schema_version_topic_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * @return array<int, array{key: string, label: string, color: string}>
     */
    public function resolvedSeriesConfig(): array
    {
        $series = $this->getAttribute('series_config');

        if (! is_array($series)) {
            return [];
        }

        $resolved = [];

        foreach ($series as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;

            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $resolved[] = [
                'key' => $key,
                'label' => is_string($entry['label'] ?? null) && trim((string) $entry['label']) !== ''
                    ? (string) $entry['label']
                    : $key,
                'color' => is_string($entry['color'] ?? null) && trim((string) $entry['color']) !== ''
                    ? (string) $entry['color']
                    : '#38bdf8',
            ];
        }

        return $resolved;
    }
}
