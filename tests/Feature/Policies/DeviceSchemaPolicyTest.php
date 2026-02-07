<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Permissions\DeviceSchemaPermission;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $organization = Organization::factory()->create();
    setPermissionsTeamId($organization->id);

    foreach (DeviceSchemaPermission::cases() as $permission) {
        Permission::findOrCreate($permission->value, 'web');
    }
});

it('allows user with viewAny permission to view device schemas index', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaPermission::VIEW_ANY->value);
    $user->assignRole($role);

    expect($user->can('viewAny', DeviceSchema::class))->toBeTrue();
});

it('denies user without viewAny permission to view device schemas index', function (): void {
    $user = User::factory()->create();

    expect($user->can('viewAny', DeviceSchema::class))->toBeFalse();
});

it('allows user with view permission to view a device schema', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaPermission::VIEW->value);
    $user->assignRole($role);

    $schema = DeviceSchema::factory()->create();

    expect($user->can('view', $schema))->toBeTrue();
});

it('denies user without view permission to view a device schema', function (): void {
    $user = User::factory()->create();
    $schema = DeviceSchema::factory()->create();

    expect($user->can('view', $schema))->toBeFalse();
});

it('allows user with create permission to create device schemas', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Creator', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaPermission::CREATE->value);
    $user->assignRole($role);

    expect($user->can('create', DeviceSchema::class))->toBeTrue();
});

it('denies user without create permission to create device schemas', function (): void {
    $user = User::factory()->create();

    expect($user->can('create', DeviceSchema::class))->toBeFalse();
});

it('allows user with update permission to update a device schema', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaPermission::UPDATE->value);
    $user->assignRole($role);

    $schema = DeviceSchema::factory()->create();

    expect($user->can('update', $schema))->toBeTrue();
});

it('denies user without update permission to update a device schema', function (): void {
    $user = User::factory()->create();
    $schema = DeviceSchema::factory()->create();

    expect($user->can('update', $schema))->toBeFalse();
});

it('allows user with delete permission to delete a device schema', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $role->givePermissionTo(DeviceSchemaPermission::DELETE->value);
    $user->assignRole($role);

    $schema = DeviceSchema::factory()->create();

    expect($user->can('delete', $schema))->toBeTrue();
});

it('denies user without delete permission to delete a device schema', function (): void {
    $user = User::factory()->create();
    $schema = DeviceSchema::factory()->create();

    expect($user->can('delete', $schema))->toBeFalse();
});

it('super admin can perform all actions on device schemas', function (): void {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $schema = DeviceSchema::factory()->create();

    expect($superAdmin->can('viewAny', DeviceSchema::class))->toBeTrue()
        ->and($superAdmin->can('view', $schema))->toBeTrue()
        ->and($superAdmin->can('create', DeviceSchema::class))->toBeTrue()
        ->and($superAdmin->can('update', $schema))->toBeTrue()
        ->and($superAdmin->can('delete', $schema))->toBeTrue();
});
