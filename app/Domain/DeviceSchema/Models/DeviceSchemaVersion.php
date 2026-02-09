<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Models;

use App\Domain\DeviceManagement\Models\Device;
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'firmware_template' => 'string',
        ];
    }

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

    /**
     * @return HasManyThrough<SchemaVersionTopicLink, SchemaVersionTopic, $this>
     */
    public function topicLinks(): HasManyThrough
    {
        return $this->hasManyThrough(
            SchemaVersionTopicLink::class,
            SchemaVersionTopic::class,
            'device_schema_version_id',
            'from_schema_version_topic_id',
            'id',
            'id',
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasFirmwareTemplate(): bool
    {
        $template = $this->getAttribute('firmware_template');

        return is_string($template) && trim($template) !== '';
    }

    public function renderFirmwareForDevice(Device $device): ?string
    {
        $template = $this->getAttribute('firmware_template');

        if (! is_string($template) || trim($template) === '') {
            return null;
        }

        $device->loadMissing('deviceType');
        $this->loadMissing('topics');

        $deviceIdentifier = is_string($device->external_id) && trim($device->external_id) !== ''
            ? $device->external_id
            : $device->uuid;
        $baseTopic = $device->deviceType?->protocol_config?->getBaseTopic() ?? 'device';

        $commandTopic = $this->topics->first(fn (SchemaVersionTopic $topic): bool => $topic->isPurposeCommand() || $topic->isSubscribe());
        $stateTopic = $this->topics->first(fn (SchemaVersionTopic $topic): bool => $topic->isPurposeState());
        $telemetryTopic = $this->topics->first(fn (SchemaVersionTopic $topic): bool => $topic->isPurposeTelemetry());
        $ackTopic = $this->topics->first(fn (SchemaVersionTopic $topic): bool => $topic->isPurposeAck());

        $replacements = [
            '{{DEVICE_ID}}' => $deviceIdentifier,
            '{{DEVICE_EXTERNAL_ID}}' => $deviceIdentifier,
            '{{DEVICE_UUID}}' => $device->uuid,
            '{{DEVICE_NAME}}' => $device->name,
            '{{MQTT_CLIENT_ID}}' => $deviceIdentifier,
            '{{BASE_TOPIC}}' => $baseTopic,
            '{{CONTROL_TOPIC}}' => $commandTopic?->resolvedTopic($device) ?? trim($baseTopic, '/')."/{$deviceIdentifier}/control",
            '{{STATE_TOPIC}}' => $stateTopic?->resolvedTopic($device) ?? trim($baseTopic, '/')."/{$deviceIdentifier}/state",
            '{{TELEMETRY_TOPIC}}' => $telemetryTopic?->resolvedTopic($device) ?? trim($baseTopic, '/')."/{$deviceIdentifier}/telemetry",
            '{{ACK_TOPIC}}' => $ackTopic?->resolvedTopic($device) ?? trim($baseTopic, '/')."/{$deviceIdentifier}/ack",
        ];

        return strtr($template, $replacements);
    }
}
