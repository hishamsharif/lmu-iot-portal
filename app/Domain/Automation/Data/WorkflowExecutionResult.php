<?php

declare(strict_types=1);

namespace App\Domain\Automation\Data;

use App\Domain\Automation\Enums\AutomationRunStatus;

class WorkflowExecutionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<string, mixed>|null  $error
     */
    public function __construct(
        public readonly AutomationRunStatus $status,
        public readonly array $steps = [],
        public readonly ?array $error = null,
    ) {}
}
