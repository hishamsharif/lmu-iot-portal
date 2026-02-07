<?php

namespace App\Policies;

use App\Domain\DeviceControl\Models\DeviceDesiredState;
use App\Domain\DeviceControl\Permissions\DeviceDesiredStatePermission;
use App\Domain\Shared\Models\User;

class DeviceDesiredStatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::VIEW_ANY);
    }

    public function view(User $user, DeviceDesiredState $model): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::CREATE);
    }

    public function update(User $user, DeviceDesiredState $model): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::UPDATE);
    }

    public function delete(User $user, DeviceDesiredState $model): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::DELETE);
    }

    public function restore(User $user, DeviceDesiredState $model): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::RESTORE);
    }

    public function forceDelete(User $user, DeviceDesiredState $model): bool
    {
        return $user->hasPermissionTo(DeviceDesiredStatePermission::FORCE_DELETE);
    }
}
