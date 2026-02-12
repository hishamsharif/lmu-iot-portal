<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Automation\Models;

use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationTelemetryTrigger>
 */
class AutomationTelemetryTriggerFactory extends Factory
{
    protected $model = AutomationTelemetryTrigger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workflow_version_id' => AutomationWorkflowVersion::factory(),
            'device_id' => null,
            'device_type_id' => null,
            'schema_version_topic_id' => null,
            'filter_expression' => null,
        ];
    }

    public function forDevice(Device $device): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'device_type_id' => $device->device_type_id,
        ]);
    }

    public function forDeviceType(DeviceType $deviceType): static
    {
        return $this->state(fn (): array => [
            'device_type_id' => $deviceType->id,
        ]);
    }

    public function forTopic(SchemaVersionTopic $topic): static
    {
        return $this->state(fn (): array => [
            'schema_version_topic_id' => $topic->id,
        ]);
    }
}
