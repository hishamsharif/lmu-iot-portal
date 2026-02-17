<?php

use App\Domain\Shared\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('iot-dashboard.organization.{organizationId}', function (User $user, int $organizationId): bool {
    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->organizations()->whereKey($organizationId)->exists();
});
