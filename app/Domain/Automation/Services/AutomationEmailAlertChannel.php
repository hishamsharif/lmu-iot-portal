<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Contracts\AutomationAlertChannel;
use App\Notifications\Automation\AutomationWorkflowAlertNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class AutomationEmailAlertChannel implements AutomationAlertChannel
{
    public function channel(): string
    {
        return 'email';
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function dispatch(array $recipients, string $subject, string $body, array $context): array
    {
        $resolvedRecipients = $this->resolveRecipients($recipients);

        if ($resolvedRecipients === []) {
            throw new RuntimeException('Alert email channel requires at least one valid recipient.');
        }

        Notification::route('mail', $resolvedRecipients)
            ->notify(new AutomationWorkflowAlertNotification(
                subject: $subject,
                body: $body,
                context: $context,
            ));

        return [
            'channel' => $this->channel(),
            'recipient_count' => count($resolvedRecipients),
            'recipients' => array_keys($resolvedRecipients),
        ];
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<string, string>
     */
    private function resolveRecipients(array $recipients): array
    {
        $resolved = [];

        foreach ($recipients as $recipient) {
            $email = trim($recipient);

            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $resolved[$email] = $email;
        }

        return $resolved;
    }
}
