<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DeviceSchemaVersionPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DeviceSchemaVersion.view-any';
    case VIEW = 'DeviceSchemaVersion.view';
    case CREATE = 'DeviceSchemaVersion.create';
    case UPDATE = 'DeviceSchemaVersion.update';
    case DELETE = 'DeviceSchemaVersion.delete';
    case RESTORE = 'DeviceSchemaVersion.restore';
    case FORCE_DELETE = 'DeviceSchemaVersion.force-delete';
}
