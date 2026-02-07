<?php

namespace App\Domain\DeviceManagement\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DeviceTypePermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DeviceType.view-any';
    case VIEW = 'DeviceType.view';
    case CREATE = 'DeviceType.create';
    case UPDATE = 'DeviceType.update';
    case DELETE = 'DeviceType.delete';
    case RESTORE = 'DeviceType.restore';
    case FORCE_DELETE = 'DeviceType.force-delete';
}
