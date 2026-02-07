<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Permissions;

use Althinect\EnumPermission\Concerns\HasPermissionGroup;

enum SchemaVersionTopicPermission: string
{
    use HasPermissionGroup;

    case VIEW_ANY = 'SchemaVersionTopic.view-any';
    case VIEW = 'SchemaVersionTopic.view';
    case CREATE = 'SchemaVersionTopic.create';
    case UPDATE = 'SchemaVersionTopic.update';
    case DELETE = 'SchemaVersionTopic.delete';
    case RESTORE = 'SchemaVersionTopic.restore';
    case FORCE_DELETE = 'SchemaVersionTopic.force-delete';
}
