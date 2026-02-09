<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\ViewDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the view device page', function (): void {
    $device = Device::factory()->create();

    livewire(ViewDevice::class, ['record' => $device->id])
        ->assertSuccessful();
});

it('shows the firmware viewer action on the device view page', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'firmware_filename' => 'esp32-device.ino',
        'firmware_template' => 'const char* DEVICE_ID = "{{DEVICE_ID}}";',
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'external_id' => 'device-101',
    ]);

    livewire(ViewDevice::class, ['record' => $device->id])
        ->assertActionExists('viewFirmware')
        ->assertActionExists('controlDashboard');
});
