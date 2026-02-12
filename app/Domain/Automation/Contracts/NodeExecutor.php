<?php

declare(strict_types=1);

namespace App\Domain\Automation\Contracts;

use App\Domain\Automation\Data\WorkflowExecutionContext;

interface NodeExecutor
{
    /**
     * @param  array<string, mixed>  $node
     */
    public function supports(array $node): bool;

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $node, array $input, WorkflowExecutionContext $context): array;
}
