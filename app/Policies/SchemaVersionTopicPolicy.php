<?php

namespace App\Policies;

use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\DeviceSchema\Permissions\SchemaVersionTopicPermission;
use App\Domain\Shared\Models\User;

class SchemaVersionTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::VIEW_ANY);
    }

    public function view(User $user, SchemaVersionTopic $model): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::CREATE);
    }

    public function update(User $user, SchemaVersionTopic $model): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::UPDATE);
    }

    public function delete(User $user, SchemaVersionTopic $model): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::DELETE);
    }

    public function restore(User $user, SchemaVersionTopic $model): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::RESTORE);
    }

    public function forceDelete(User $user, SchemaVersionTopic $model): bool
    {
        return $user->hasPermissionTo(SchemaVersionTopicPermission::FORCE_DELETE);
    }
}
