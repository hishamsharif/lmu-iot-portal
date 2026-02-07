<?php

namespace App\Policies;

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Permissions\DeviceTypePermission;
use App\Domain\Shared\Models\User;

class DeviceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::VIEW_ANY);
    }

    public function view(User $user, DeviceType $model): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::CREATE);
    }

    public function update(User $user, DeviceType $model): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::UPDATE);
    }

    public function delete(User $user, DeviceType $model): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::DELETE);
    }

    public function restore(User $user, DeviceType $model): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::RESTORE);
    }

    public function forceDelete(User $user, DeviceType $model): bool
    {
        return $user->hasPermissionTo(DeviceTypePermission::FORCE_DELETE);
    }
}
