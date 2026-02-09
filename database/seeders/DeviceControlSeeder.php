<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Seeder;

class DeviceControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::first() ?? Organization::factory()->create();

        $this->seedDeviceForType(
            organizationId: $organization->id,
            deviceTypeKey: 'dimmable_light',
            externalId: 'dimmable-light-01',
            name: 'Lobby Dimmable Light',
            metadata: [
                'location' => 'Main Lobby',
                'model' => 'DL-10',
            ],
        );

        $this->seedDeviceForType(
            organizationId: $organization->id,
            deviceTypeKey: 'rgb_led_controller',
            externalId: 'rgb-led-01',
            name: 'Entrance RGB LED Strip',
            metadata: [
                'location' => 'Entrance Canopy',
                'model' => 'RGB-3000',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function seedDeviceForType(
        int $organizationId,
        string $deviceTypeKey,
        string $externalId,
        string $name,
        array $metadata,
    ): void {
        $deviceType = DeviceType::query()
            ->where('key', $deviceTypeKey)
            ->first();

        if ($deviceType === null) {
            $this->command?->warn("{$deviceTypeKey} device type not found. Please run DeviceSchemaSeeder first.");

            return;
        }

        $schema = DeviceSchema::query()
            ->where('device_type_id', $deviceType->id)
            ->first();
        $version = $schema?->versions()->where('status', 'active')->first();

        if ($version === null) {
            $this->command?->warn("Active schema version for {$deviceTypeKey} not found.");

            return;
        }

        Device::firstOrCreate([
            'organization_id' => $organizationId,
            'device_type_id' => $deviceType->id,
            'external_id' => $externalId,
        ], [
            'name' => $name,
            'device_schema_version_id' => $version->id,
            'metadata' => $metadata,
            'is_active' => true,
        ]);
    }
}
