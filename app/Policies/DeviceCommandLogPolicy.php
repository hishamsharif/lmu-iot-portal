<?php

namespace App\Policies;

use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Permissions\DeviceCommandLogPermission;
use App\Domain\Shared\Models\User;

class DeviceCommandLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::VIEW_ANY);
    }

    public function view(User $user, DeviceCommandLog $model): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::CREATE);
    }

    public function update(User $user, DeviceCommandLog $model): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::UPDATE);
    }

    public function delete(User $user, DeviceCommandLog $model): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::DELETE);
    }

    public function restore(User $user, DeviceCommandLog $model): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::RESTORE);
    }

    public function forceDelete(User $user, DeviceCommandLog $model): bool
    {
        return $user->hasPermissionTo(DeviceCommandLogPermission::FORCE_DELETE);
    }
}
