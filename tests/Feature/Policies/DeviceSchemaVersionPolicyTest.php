<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Permissions\DeviceSchemaVersionPermission;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $organization = Organization::factory()->create();
    setPermissionsTeamId($organization->id);

    foreach (DeviceSchemaVersionPermission::cases() as $permission) {
        Permission::findOrCreate($permission->value, 'web');
    }
});

it('allows user with viewAny permission to view schema versions index', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaVersionPermission::VIEW_ANY->value);
    $user->assignRole($role);

    expect($user->can('viewAny', DeviceSchemaVersion::class))->toBeTrue();
});

it('denies user without viewAny permission to view schema versions index', function (): void {
    $user = User::factory()->create();

    expect($user->can('viewAny', DeviceSchemaVersion::class))->toBeFalse();
});

it('allows user with view permission to view a schema version', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaVersionPermission::VIEW->value);
    $user->assignRole($role);

    $version = DeviceSchemaVersion::factory()->create();

    expect($user->can('view', $version))->toBeTrue();
});

it('denies user without view permission to view a schema version', function (): void {
    $user = User::factory()->create();
    $version = DeviceSchemaVersion::factory()->create();

    expect($user->can('view', $version))->toBeFalse();
});

it('allows user with create permission to create schema versions', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Creator', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaVersionPermission::CREATE->value);
    $user->assignRole($role);

    expect($user->can('create', DeviceSchemaVersion::class))->toBeTrue();
});

it('denies user without create permission to create schema versions', function (): void {
    $user = User::factory()->create();

    expect($user->can('create', DeviceSchemaVersion::class))->toBeFalse();
});

it('allows user with update permission to update a schema version', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaVersionPermission::UPDATE->value);
    $user->assignRole($role);

    $version = DeviceSchemaVersion::factory()->create();

    expect($user->can('update', $version))->toBeTrue();
});

it('denies user without update permission to update a schema version', function (): void {
    $user = User::factory()->create();
    $version = DeviceSchemaVersion::factory()->create();

    expect($user->can('update', $version))->toBeFalse();
});

it('allows user with delete permission to delete a schema version', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaVersionPermission::DELETE->value);
    $user->assignRole($role);

    $version = DeviceSchemaVersion::factory()->create();

    expect($user->can('delete', $version))->toBeTrue();
});

it('denies user without delete permission to delete a schema version', function (): void {
    $user = User::factory()->create();
    $version = DeviceSchemaVersion::factory()->create();

    expect($user->can('delete', $version))->toBeFalse();
});

it('super admin can perform all actions on schema versions', function (): void {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $version = DeviceSchemaVersion::factory()->create();

    expect($superAdmin->can('viewAny', DeviceSchemaVersion::class))->toBeTrue()
        ->and($superAdmin->can('view', $version))->toBeTrue()
        ->and($superAdmin->can('create', DeviceSchemaVersion::class))->toBeTrue()
        ->and($superAdmin->can('update', $version))->toBeTrue()
        ->and($superAdmin->can('delete', $version))->toBeTrue();
});
