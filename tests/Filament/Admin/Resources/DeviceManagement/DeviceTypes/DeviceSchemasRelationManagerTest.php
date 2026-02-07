<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\EditDeviceType;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\ViewDeviceType;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\RelationManagers\DeviceSchemasRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    $this->deviceType = DeviceType::factory()->mqtt()->create();

    DeviceSchemasRelationManager::skipAuthorization();
});

afterEach(function (): void {
    DeviceSchemasRelationManager::skipAuthorization(false);
});

it('can render the device schemas relation manager', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertOk();
});

it('is rendered on the view device type page', function (): void {
    livewire(ViewDeviceType::class, [
        'record' => $this->deviceType->id,
    ])
        ->assertSeeLivewire(DeviceSchemasRelationManager::class);
});

it('can list existing schemas', function (): void {
    $schemas = DeviceSchema::factory()->count(3)->forDeviceType($this->deviceType)->create();

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertCanSeeTableRecords($schemas);
});

it('does not show schemas from other device types', function (): void {
    $otherDeviceType = DeviceType::factory()->mqtt()->create();
    $otherSchema = DeviceSchema::factory()->forDeviceType($otherDeviceType)->create();
    $ownSchema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertCanSeeTableRecords([$ownSchema])
        ->assertCanNotSeeTableRecords([$otherSchema]);
});

it('can create a schema', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => EditDeviceType::class,
    ])
        ->callTableAction('create', data: [
            'name' => 'Thermal Sensor Contract',
        ])
        ->assertHasNoFormErrors();

    expect(DeviceSchema::where([
        'device_type_id' => $this->deviceType->id,
        'name' => 'Thermal Sensor Contract',
    ])->exists())->toBeTrue();
});

it('validates name is required', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => EditDeviceType::class,
    ])
        ->callTableAction('create', data: [
            'name' => null,
        ])
        ->assertHasFormErrors(['name' => 'required']);
});

it('can search schemas by name', function (): void {
    $schema1 = DeviceSchema::factory()->forDeviceType($this->deviceType)->create(['name' => 'Thermal Contract']);
    $schema2 = DeviceSchema::factory()->forDeviceType($this->deviceType)->create(['name' => 'Network Contract']);

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->searchTable('Thermal')
        ->assertCanSeeTableRecords([$schema1])
        ->assertCanNotSeeTableRecords([$schema2]);
});
