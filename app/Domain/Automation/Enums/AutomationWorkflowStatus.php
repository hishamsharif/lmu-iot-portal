<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

enum AutomationWorkflowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
