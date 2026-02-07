<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum DerivedParameterDefinitionPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'DerivedParameterDefinition.view-any';
    case VIEW = 'DerivedParameterDefinition.view';
    case CREATE = 'DerivedParameterDefinition.create';
    case UPDATE = 'DerivedParameterDefinition.update';
    case DELETE = 'DerivedParameterDefinition.delete';
    case RESTORE = 'DerivedParameterDefinition.restore';
    case FORCE_DELETE = 'DerivedParameterDefinition.force-delete';
}
