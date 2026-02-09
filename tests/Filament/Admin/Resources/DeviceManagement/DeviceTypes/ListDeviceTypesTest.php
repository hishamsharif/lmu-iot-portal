<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\ListDeviceTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the device types list page', function (): void {
    livewire(ListDeviceTypes::class)
        ->assertSuccessful();
});

it('can see device types in the table', function (): void {
    $deviceTypes = DeviceType::factory()->global()->count(3)->create();

    livewire(ListDeviceTypes::class)
        ->assertCanSeeTableRecords($deviceTypes);
});

it('can search for device types by name', function (): void {
    $type1 = DeviceType::factory()->global()->create(['name' => 'MQTT Energy Meter']);
    $type2 = DeviceType::factory()->global()->create(['name' => 'HTTP Temperature Sensor']);

    livewire(ListDeviceTypes::class)
        ->searchTable($type1->name)
        ->assertCanSeeTableRecords([$type1])
        ->assertCanNotSeeTableRecords([$type2]);
});

it('can search for device types by key', function (): void {
    $type1 = DeviceType::factory()->global()->create(['key' => 'mqtt_meter']);
    $type2 = DeviceType::factory()->global()->create(['key' => 'http_sensor']);

    livewire(ListDeviceTypes::class)
        ->searchTable($type1->key)
        ->assertCanSeeTableRecords([$type1])
        ->assertCanNotSeeTableRecords([$type2]);
});

it('can filter device types by protocol', function (): void {
    $mqttType = DeviceType::factory()->mqtt()->create();
    $httpType = DeviceType::factory()->http()->create();

    livewire(ListDeviceTypes::class)
        ->filterTable('default_protocol', ProtocolType::Mqtt->value)
        ->assertCanSeeTableRecords([$mqttType])
        ->assertCanNotSeeTableRecords([$httpType]);
});

it('can filter device types by scope (global)', function (): void {
    $globalType = DeviceType::factory()->global()->create();
    $orgType = DeviceType::factory()->forOrganization(Organization::factory()->create()->id)->create();

    livewire(ListDeviceTypes::class)
        ->filterTable('organization_id', 'global')
        ->assertCanSeeTableRecords([$globalType])
        ->assertCanNotSeeTableRecords([$orgType]);
});

it('can filter device types by scope (organization)', function (): void {
    $globalType = DeviceType::factory()->global()->create();
    $orgType = DeviceType::factory()->forOrganization(Organization::factory()->create()->id)->create();

    livewire(ListDeviceTypes::class)
        ->filterTable('organization_id', 'organization')
        ->assertCanSeeTableRecords([$orgType])
        ->assertCanNotSeeTableRecords([$globalType]);
});

it('displays no records message when empty', function (): void {
    livewire(ListDeviceTypes::class)
        ->assertCountTableRecords(0);
});

it('can delete a device type', function (): void {
    $deviceType = DeviceType::factory()->global()->create();

    livewire(ListDeviceTypes::class)
        ->callTableAction('delete', $deviceType);

    $this->assertModelMissing($deviceType);
});

it('can replicate a device type with a generated scoped key', function (): void {
    $deviceType = DeviceType::factory()->global()->create([
        'key' => 'smart_light',
        'name' => 'Smart Light',
    ]);

    livewire(ListDeviceTypes::class)
        ->callTableAction('replicate', $deviceType);

    $replica = DeviceType::query()
        ->where('id', '!=', $deviceType->id)
        ->latest('id')
        ->first();

    expect($replica)->not->toBeNull()
        ->and($replica?->key)->toStartWith('smart_light_copy')
        ->and($replica?->name)->toBe('Smart Light Copy')
        ->and($replica?->organization_id)->toBeNull();
});
