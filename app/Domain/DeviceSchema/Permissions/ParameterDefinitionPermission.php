<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum ParameterDefinitionPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'ParameterDefinition.view-any';
    case VIEW = 'ParameterDefinition.view';
    case CREATE = 'ParameterDefinition.create';
    case UPDATE = 'ParameterDefinition.update';
    case DELETE = 'ParameterDefinition.delete';
    case RESTORE = 'ParameterDefinition.restore';
    case FORCE_DELETE = 'ParameterDefinition.force-delete';
}
