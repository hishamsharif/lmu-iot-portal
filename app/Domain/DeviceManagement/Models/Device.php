<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Models;

use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Models\DeviceDesiredState;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Database\Factories\Domain\DeviceManagement\Models\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\DeviceManagement\Models\DeviceFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $device): void {
            if (! $device->uuid) {
                $device->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): DeviceFactory
    {
        return DeviceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_simulated' => 'bool',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<DeviceType, $this>
     */
    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    /**
     * @return BelongsTo<DeviceSchemaVersion, $this>
     */
    public function schemaVersion(): BelongsTo
    {
        return $this->belongsTo(DeviceSchemaVersion::class, 'device_schema_version_id');
    }

    /**
     * @return HasMany<DeviceTelemetryLog, $this>
     */
    public function telemetryLogs(): HasMany
    {
        return $this->hasMany(DeviceTelemetryLog::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<DeviceDesiredState, $this>
     */
    public function desiredState(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeviceDesiredState::class);
    }

    /**
     * @return HasMany<DeviceCommandLog, $this>
     */
    public function commandLogs(): HasMany
    {
        return $this->hasMany(DeviceCommandLog::class);
    }
}
