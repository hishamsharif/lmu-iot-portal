<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Models;

use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class DeviceSchemaVersion extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\DeviceSchema\Models\DeviceSchemaVersionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<DeviceSchema, $this>
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(DeviceSchema::class, 'device_schema_id');
    }

    /**
     * @return HasMany<SchemaVersionTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(SchemaVersionTopic::class, 'device_schema_version_id');
    }

    /**
     * @return HasManyThrough<ParameterDefinition, SchemaVersionTopic, $this>
     */
    public function parameters(): HasManyThrough
    {
        return $this->hasManyThrough(
            ParameterDefinition::class,
            SchemaVersionTopic::class,
            'device_schema_version_id',
            'schema_version_topic_id',
        );
    }

    /**
     * @return HasMany<DerivedParameterDefinition, $this>
     */
    public function derivedParameters(): HasMany
    {
        return $this->hasMany(DerivedParameterDefinition::class, 'device_schema_version_id');
    }

    /**
     * @return HasMany<DeviceTelemetryLog, $this>
     */
    public function telemetryLogs(): HasMany
    {
        return $this->hasMany(DeviceTelemetryLog::class, 'device_schema_version_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
