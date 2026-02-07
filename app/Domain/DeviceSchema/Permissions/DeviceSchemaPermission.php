<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DeviceSchemaPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DeviceSchema.view-any';
    case VIEW = 'DeviceSchema.view';
    case CREATE = 'DeviceSchema.create';
    case UPDATE = 'DeviceSchema.update';
    case DELETE = 'DeviceSchema.delete';
    case RESTORE = 'DeviceSchema.restore';
    case FORCE_DELETE = 'DeviceSchema.force-delete';
}
