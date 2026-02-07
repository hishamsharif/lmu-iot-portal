<?php

namespace App\Policies;

use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Permissions\DerivedParameterDefinitionPermission;
use App\Domain\Shared\Models\User;

class DerivedParameterDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::VIEW_ANY);
    }

    public function view(User $user, DerivedParameterDefinition $derivedParameterDefinition): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::CREATE);
    }

    public function update(User $user, DerivedParameterDefinition $derivedParameterDefinition): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::UPDATE);
    }

    public function delete(User $user, DerivedParameterDefinition $derivedParameterDefinition): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::DELETE);
    }

    public function restore(User $user, DerivedParameterDefinition $derivedParameterDefinition): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::RESTORE);
    }

    public function forceDelete(User $user, DerivedParameterDefinition $derivedParameterDefinition): bool
    {
        return $user->hasPermissionTo(DerivedParameterDefinitionPermission::FORCE_DELETE);
    }
}
