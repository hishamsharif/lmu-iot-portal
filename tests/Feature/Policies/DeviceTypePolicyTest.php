<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Permissions\DeviceTypePermission;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $organization = Organization::factory()->create();
    setPermissionsTeamId($organization->id);

    $this->user = User::factory()->create();

    foreach (DeviceTypePermission::cases() as $permission) {
        Permission::findOrCreate($permission->value, 'web');
    }
});

it('allows user with viewAny permission to view device types index', function (): void {
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceTypePermission::VIEW_ANY->value);
    $this->user->assignRole($role);

    expect($this->user->can('viewAny', DeviceType::class))->toBeTrue();
});

it('denies user without viewAny permission to view device types index', function (): void {
    expect($this->user->can('viewAny', DeviceType::class))->toBeFalse();
});

it('allows user with view permission to view a device type', function (): void {
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceTypePermission::VIEW->value);
    $this->user->assignRole($role);

    $deviceType = DeviceType::factory()->global()->create();

    expect($this->user->can('view', $deviceType))->toBeTrue();
});

it('denies user without view permission to view a device type', function (): void {
    $deviceType = DeviceType::factory()->global()->create();

    expect($this->user->can('view', $deviceType))->toBeFalse();
});

it('allows user with create permission to create device types', function (): void {
    $role = Role::create(['name' => 'Creator', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceTypePermission::CREATE->value);
    $this->user->assignRole($role);

    expect($this->user->can('create', DeviceType::class))->toBeTrue();
});

it('denies user without create permission to create device types', function (): void {
    expect($this->user->can('create', DeviceType::class))->toBeFalse();
});

it('allows user with update permission to update a device type', function (): void {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceTypePermission::UPDATE->value);
    $this->user->assignRole($role);

    $deviceType = DeviceType::factory()->global()->create();

    expect($this->user->can('update', $deviceType))->toBeTrue();
});

it('denies user without update permission to update a device type', function (): void {
    $deviceType = DeviceType::factory()->global()->create();

    expect($this->user->can('update', $deviceType))->toBeFalse();
});

it('allows user with delete permission to delete a device type', function (): void {
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceTypePermission::DELETE->value);
    $this->user->assignRole($role);

    $deviceType = DeviceType::factory()->global()->create();

    expect($this->user->can('delete', $deviceType))->toBeTrue();
});

it('denies user without delete permission to delete a device type', function (): void {
    $deviceType = DeviceType::factory()->global()->create();

    expect($this->user->can('delete', $deviceType))->toBeFalse();
});

it('super admin can perform all actions', function (): void {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $deviceType = DeviceType::factory()->global()->create();

    expect($superAdmin->can('viewAny', DeviceType::class))->toBeTrue()
        ->and($superAdmin->can('view', $deviceType))->toBeTrue()
        ->and($superAdmin->can('create', DeviceType::class))->toBeTrue()
        ->and($superAdmin->can('update', $deviceType))->toBeTrue()
        ->and($superAdmin->can('delete', $deviceType))->toBeTrue();
});
