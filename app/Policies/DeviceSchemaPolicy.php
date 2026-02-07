<?php

namespace App\Policies;

use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Permissions\DeviceSchemaPermission;
use App\Domain\Shared\Models\User;

class DeviceSchemaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::VIEW_ANY);
    }

    public function view(User $user, DeviceSchema $deviceSchema): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::CREATE);
    }

    public function update(User $user, DeviceSchema $deviceSchema): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::UPDATE);
    }

    public function delete(User $user, DeviceSchema $deviceSchema): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::DELETE);
    }

    public function restore(User $user, DeviceSchema $deviceSchema): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::RESTORE);
    }

    public function forceDelete(User $user, DeviceSchema $deviceSchema): bool
    {
        return $user->hasPermissionTo(DeviceSchemaPermission::FORCE_DELETE);
    }
}
