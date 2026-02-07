<?php

declare(strict_types=1);

namespace App\Domain\Telemetry\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DeviceTelemetryLogPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DeviceTelemetryLog.view-any';
    case VIEW = 'DeviceTelemetryLog.view';
    case CREATE = 'DeviceTelemetryLog.create';
    case UPDATE = 'DeviceTelemetryLog.update';
    case DELETE = 'DeviceTelemetryLog.delete';
    case RESTORE = 'DeviceTelemetryLog.restore';
    case FORCE_DELETE = 'DeviceTelemetryLog.force-delete';
}
