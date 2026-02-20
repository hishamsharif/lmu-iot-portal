<?php

declare(strict_types=1);

namespace App\Domain\Automation\Data;

class WorkflowExecutionContext
{
    /**
     * @param  array<string, mixed>  $triggerPayload
     * @param  array<string, mixed>  $query
     * @param  array<string, array<string, mixed>>  $queries
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly string $triggerType,
        public readonly array $triggerPayload = [],
        public readonly array $query = [],
        public readonly array $queries = [],
        public readonly array $variables = [],
        public readonly ?int $deviceId = null,
        public readonly ?int $topicId = null,
        public readonly ?string $runUuid = null,
    ) {}
}
