<?php

namespace App\Policies;

use App\Domain\Shared\Models\User;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Domain\Telemetry\Permissions\DeviceTelemetryLogPermission;

class DeviceTelemetryLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::VIEW_ANY);
    }

    public function view(User $user, DeviceTelemetryLog $deviceTelemetryLog): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::CREATE);
    }

    public function update(User $user, DeviceTelemetryLog $deviceTelemetryLog): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::UPDATE);
    }

    public function delete(User $user, DeviceTelemetryLog $deviceTelemetryLog): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::DELETE);
    }

    public function restore(User $user, DeviceTelemetryLog $deviceTelemetryLog): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::RESTORE);
    }

    public function forceDelete(User $user, DeviceTelemetryLog $deviceTelemetryLog): bool
    {
        return $user->hasPermissionTo(DeviceTelemetryLogPermission::FORCE_DELETE);
    }
}
