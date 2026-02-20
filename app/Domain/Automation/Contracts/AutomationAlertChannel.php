<?php

declare(strict_types=1);

namespace App\Domain\Automation\Contracts;

interface AutomationAlertChannel
{
    public function channel(): string;

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function dispatch(array $recipients, string $subject, string $body, array $context): array;
}
