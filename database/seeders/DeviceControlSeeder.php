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

        $deviceType = DeviceType::where('key', 'dimmable_light')->first();

        if (! $deviceType) {
            $this->command->warn('Dimmable Light DeviceType not found. Please run DeviceSchemaSeeder first.');

            return;
        }

        $schema = DeviceSchema::where('device_type_id', $deviceType->id)->first();
        $version = $schema?->versions()->where('status', 'active')->first();

        if (! $version) {
            $this->command->warn('Active schema version for Dimmable Light not found.');

            return;
        }

        Device::firstOrCreate([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'external_id' => 'dimmable-light-01',
        ], [
            'name' => 'Lobby Dimmable Light',
            'device_schema_version_id' => $version->id,
            'metadata' => [
                'location' => 'Main Lobby',
                'model' => 'DL-10',
            ],
            'is_active' => true,
        ]);
    }
}
