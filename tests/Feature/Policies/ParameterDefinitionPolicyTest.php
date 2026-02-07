<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Permissions\ParameterDefinitionPermission;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $organization = Organization::factory()->create();
    setPermissionsTeamId($organization->id);

    foreach (ParameterDefinitionPermission::cases() as $permission) {
        Permission::findOrCreate($permission->value, 'web');
    }
});

it('allows user with viewAny permission to view parameter definitions index', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(ParameterDefinitionPermission::VIEW_ANY->value);
    $user->assignRole($role);

    expect($user->can('viewAny', ParameterDefinition::class))->toBeTrue();
});

it('denies user without viewAny permission to view parameter definitions index', function (): void {
    $user = User::factory()->create();

    expect($user->can('viewAny', ParameterDefinition::class))->toBeFalse();
});

it('allows user with view permission to view a parameter definition', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(ParameterDefinitionPermission::VIEW->value);
    $user->assignRole($role);

    $parameter = ParameterDefinition::factory()->create();

    expect($user->can('view', $parameter))->toBeTrue();
});

it('denies user without view permission to view a parameter definition', function (): void {
    $user = User::factory()->create();
    $parameter = ParameterDefinition::factory()->create();

    expect($user->can('view', $parameter))->toBeFalse();
});

it('allows user with create permission to create parameter definitions', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Creator', 'guard_name' => 'web']);
    $role->givePermissionTo(ParameterDefinitionPermission::CREATE->value);
    $user->assignRole($role);

    expect($user->can('create', ParameterDefinition::class))->toBeTrue();
});

it('denies user without create permission to create parameter definitions', function (): void {
    $user = User::factory()->create();

    expect($user->can('create', ParameterDefinition::class))->toBeFalse();
});

it('allows user with update permission to update a parameter definition', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $role->givePermissionTo(ParameterDefinitionPermission::UPDATE->value);
    $user->assignRole($role);

    $parameter = ParameterDefinition::factory()->create();

    expect($user->can('update', $parameter))->toBeTrue();
});

it('denies user without update permission to update a parameter definition', function (): void {
    $user = User::factory()->create();
    $parameter = ParameterDefinition::factory()->create();

    expect($user->can('update', $parameter))->toBeFalse();
});

it('allows user with delete permission to delete a parameter definition', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $role->givePermissionTo(ParameterDefinitionPermission::DELETE->value);
    $user->assignRole($role);

    $parameter = ParameterDefinition::factory()->create();

    expect($user->can('delete', $parameter))->toBeTrue();
});

it('denies user without delete permission to delete a parameter definition', function (): void {
    $user = User::factory()->create();
    $parameter = ParameterDefinition::factory()->create();

    expect($user->can('delete', $parameter))->toBeFalse();
});

it('super admin can perform all actions on parameter definitions', function (): void {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $parameter = ParameterDefinition::factory()->create();

    expect($superAdmin->can('viewAny', ParameterDefinition::class))->toBeTrue()
        ->and($superAdmin->can('view', $parameter))->toBeTrue()
        ->and($superAdmin->can('create', ParameterDefinition::class))->toBeTrue()
        ->and($superAdmin->can('update', $parameter))->toBeTrue()
        ->and($superAdmin->can('delete', $parameter))->toBeTrue();
});
