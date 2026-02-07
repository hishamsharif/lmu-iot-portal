<?php

namespace App\Policies;

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Permissions\DeviceSchemaVersionPermission;
use App\Domain\Shared\Models\User;

class DeviceSchemaVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::VIEW_ANY);
    }

    public function view(User $user, DeviceSchemaVersion $deviceSchemaVersion): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::CREATE);
    }

    public function update(User $user, DeviceSchemaVersion $deviceSchemaVersion): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::UPDATE);
    }

    public function delete(User $user, DeviceSchemaVersion $deviceSchemaVersion): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::DELETE);
    }

    public function restore(User $user, DeviceSchemaVersion $deviceSchemaVersion): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::RESTORE);
    }

    public function forceDelete(User $user, DeviceSchemaVersion $deviceSchemaVersion): bool
    {
        return $user->hasPermissionTo(DeviceSchemaVersionPermission::FORCE_DELETE);
    }
}
