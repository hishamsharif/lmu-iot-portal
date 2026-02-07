<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DeviceDesiredStatePermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DeviceDesiredState.view-any';
    case VIEW = 'DeviceDesiredState.view';
    case CREATE = 'DeviceDesiredState.create';
    case UPDATE = 'DeviceDesiredState.update';
    case DELETE = 'DeviceDesiredState.delete';
    case RESTORE = 'DeviceDesiredState.restore';
    case FORCE_DELETE = 'DeviceDesiredState.force-delete';
}
