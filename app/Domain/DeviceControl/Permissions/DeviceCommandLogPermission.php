<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DeviceCommandLogPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DeviceCommandLog.view-any';
    case VIEW = 'DeviceCommandLog.view';
    case CREATE = 'DeviceCommandLog.create';
    case UPDATE = 'DeviceCommandLog.update';
    case DELETE = 'DeviceCommandLog.delete';
    case RESTORE = 'DeviceCommandLog.restore';
    case FORCE_DELETE = 'DeviceCommandLog.force-delete';
}
