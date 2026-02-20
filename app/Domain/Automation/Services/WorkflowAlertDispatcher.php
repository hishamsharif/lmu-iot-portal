<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Contracts\AutomationAlertChannel;
use RuntimeException;

class WorkflowAlertDispatcher
{
    /**
     * @var array<string, AutomationAlertChannel>
     */
    private array $channels;

    public function __construct(
        AutomationEmailAlertChannel $emailAlertChannel,
    ) {
        $this->channels = [
            $emailAlertChannel->channel() => $emailAlertChannel,
        ];
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function dispatch(string $channel, array $recipients, string $subject, string $body, array $context = []): array
    {
        $resolvedChannel = $this->channels[$channel] ?? null;

        if (! $resolvedChannel instanceof AutomationAlertChannel) {
            throw new RuntimeException("Unsupported alert channel [{$channel}].");
        }

        return $resolvedChannel->dispatch(
            recipients: $recipients,
            subject: $subject,
            body: $body,
            context: $context,
        );
    }

    /**
     * @return array<int, string>
     */
    public function supportedChannels(): array
    {
        return array_keys($this->channels);
    }
}
