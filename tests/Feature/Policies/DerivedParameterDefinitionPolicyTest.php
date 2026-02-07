<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Permissions\DerivedParameterDefinitionPermission;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $organization = Organization::factory()->create();
    setPermissionsTeamId($organization->id);

    foreach (DerivedParameterDefinitionPermission::cases() as $permission) {
        Permission::findOrCreate($permission->value, 'web');
    }
});

it('allows user with viewAny permission to view derived parameters index', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DerivedParameterDefinitionPermission::VIEW_ANY->value);
    $user->assignRole($role);

    expect($user->can('viewAny', DerivedParameterDefinition::class))->toBeTrue();
});

it('denies user without viewAny permission to view derived parameters index', function (): void {
    $user = User::factory()->create();

    expect($user->can('viewAny', DerivedParameterDefinition::class))->toBeFalse();
});

it('allows user with view permission to view a derived parameter', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(DerivedParameterDefinitionPermission::VIEW->value);
    $user->assignRole($role);

    $definition = DerivedParameterDefinition::factory()->create();

    expect($user->can('view', $definition))->toBeTrue();
});

it('denies user without view permission to view a derived parameter', function (): void {
    $user = User::factory()->create();
    $definition = DerivedParameterDefinition::factory()->create();

    expect($user->can('view', $definition))->toBeFalse();
});

it('allows user with create permission to create derived parameters', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Creator', 'guard_name' => 'web']);
    $role->givePermissionTo(DerivedParameterDefinitionPermission::CREATE->value);
    $user->assignRole($role);

    expect($user->can('create', DerivedParameterDefinition::class))->toBeTrue();
});

it('denies user without create permission to create derived parameters', function (): void {
    $user = User::factory()->create();

    expect($user->can('create', DerivedParameterDefinition::class))->toBeFalse();
});

it('allows user with update permission to update a derived parameter', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $role->givePermissionTo(DerivedParameterDefinitionPermission::UPDATE->value);
    $user->assignRole($role);

    $definition = DerivedParameterDefinition::factory()->create();

    expect($user->can('update', $definition))->toBeTrue();
});

it('denies user without update permission to update a derived parameter', function (): void {
    $user = User::factory()->create();
    $definition = DerivedParameterDefinition::factory()->create();

    expect($user->can('update', $definition))->toBeFalse();
});

it('allows user with delete permission to delete a derived parameter', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $role->givePermissionTo(DerivedParameterDefinitionPermission::DELETE->value);
    $user->assignRole($role);

    $definition = DerivedParameterDefinition::factory()->create();

    expect($user->can('delete', $definition))->toBeTrue();
});

it('denies user without delete permission to delete a derived parameter', function (): void {
    $user = User::factory()->create();
    $definition = DerivedParameterDefinition::factory()->create();

    expect($user->can('delete', $definition))->toBeFalse();
});

it('super admin can perform all actions on derived parameters', function (): void {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $definition = DerivedParameterDefinition::factory()->create();

    expect($superAdmin->can('viewAny', DerivedParameterDefinition::class))->toBeTrue()
        ->and($superAdmin->can('view', $definition))->toBeTrue()
        ->and($superAdmin->can('create', DerivedParameterDefinition::class))->toBeTrue()
        ->and($superAdmin->can('update', $definition))->toBeTrue()
        ->and($superAdmin->can('delete', $definition))->toBeTrue();
});
