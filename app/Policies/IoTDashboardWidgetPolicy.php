<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\User;

class IoTDashboardWidgetPolicy
{
    public function view(User $user, IoTDashboardWidget $widget): bool
    {
        $widget->loadMissing('dashboard');
        $dashboard = $widget->dashboard;

        if ($dashboard === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->organizations()->whereKey($dashboard->organization_id)->exists();
    }

    public function update(User $user, IoTDashboardWidget $widget): bool
    {
        return $this->view($user, $widget);
    }

    public function delete(User $user, IoTDashboardWidget $widget): bool
    {
        return $this->view($user, $widget);
    }
}
