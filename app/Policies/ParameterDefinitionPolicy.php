<?php

namespace App\Policies;

use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Permissions\ParameterDefinitionPermission;
use App\Domain\Shared\Models\User;

class ParameterDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::VIEW_ANY);
    }

    public function view(User $user, ParameterDefinition $parameterDefinition): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::CREATE);
    }

    public function update(User $user, ParameterDefinition $parameterDefinition): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::UPDATE);
    }

    public function delete(User $user, ParameterDefinition $parameterDefinition): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::DELETE);
    }

    public function restore(User $user, ParameterDefinition $parameterDefinition): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::RESTORE);
    }

    public function forceDelete(User $user, ParameterDefinition $parameterDefinition): bool
    {
        return $user->hasPermissionTo(ParameterDefinitionPermission::FORCE_DELETE);
    }
}
