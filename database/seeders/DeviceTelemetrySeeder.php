<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Services\TelemetryLogRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DeviceTelemetrySeeder extends Seeder
{
    public function __construct(protected TelemetryLogRecorder $recorder) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::first() ?? Organization::factory()->create();

        $deviceType = DeviceType::where('key', 'energy_meter')->first();

        if (! $deviceType) {
            $this->command->warn('Energy Meter DeviceType not found. Please run DeviceSchemaSeeder first.');

            return;
        }

        $schema = DeviceSchema::where('device_type_id', $deviceType->id)->first();
        $version = $schema?->versions()->where('status', 'active')->first();

        if (! $version) {
            $this->command->warn('Active schema version for Energy Meter not found.');

            return;
        }

        $device = Device::firstOrCreate([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'external_id' => 'main-energy-meter-01',
        ], [
            'name' => 'Main Building Energy Meter',
            'device_schema_version_id' => $version->id,
            'metadata' => [
                'location' => 'Main Electrical Room',
                'model' => 'EM-3PH-400',
            ],
            'is_active' => true,
        ]);

        $this->command->info("Generating telemetry history for device: {$device->name}");

        $now = Carbon::now();
        $startDate = $now->copy()->subDay()->startOfDay();
        $stepMinutes = 15;
        $totalSteps = (int) floor($now->diffInMinutes($startDate) / $stepMinutes) + 1;

        $currentDate = $startDate->copy();

        $bar = $this->command->getOutput()->createProgressBar($totalSteps);
        $bar->start();

        while ($currentDate->lessThanOrEqualTo($now)) {
            $hour = (int) $currentDate->format('H');
            $loadMultiplier = ($hour >= 8 && $hour <= 18) ? 2.5 : 0.8;
            $randomNoise = rand(80, 120) / 100;

            $payload = [
                'voltages' => [
                    'V1' => 230 + (rand(-50, 50) / 10),
                    'V2' => 229 + (rand(-50, 50) / 10),
                    'V3' => 231 + (rand(-50, 50) / 10),
                ],
                'power' => [
                    'power_l1' => (1500 * $loadMultiplier * $randomNoise) + rand(-100, 100),
                    'power_l2' => (1200 * $loadMultiplier * $randomNoise) + rand(-100, 100),
                    'power_l3' => (1800 * $loadMultiplier * $randomNoise) + rand(-100, 100),
                ],
            ];

            $payload['power']['power_l1'] = max(0, $payload['power']['power_l1']);
            $payload['power']['power_l2'] = max(0, $payload['power']['power_l2']);
            $payload['power']['power_l3'] = max(0, $payload['power']['power_l3']);

            $this->recorder->record(
                device: $device,
                payload: $payload,
                recordedAt: $currentDate->copy(),
                receivedAt: $currentDate->copy()->addSeconds(rand(1, 5)),
                topicSuffix: 'telemetry',
            );

            $currentDate->addMinutes($stepMinutes);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('Telemetry history generated successfully.');
    }
}
