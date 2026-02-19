<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Shared\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;

class ReportRunNotificationService
{
    public function sendForStatus(ReportRun $reportRun): void
    {
        $reportRun->loadMissing(['requestedBy:id,name', 'device:id,name']);

        $recipient = $reportRun->requestedBy;

        if (! $recipient instanceof User) {
            return;
        }

        $notification = match ($reportRun->status) {
            ReportRunStatus::Completed => Notification::make()
                ->title('Report is ready')
                ->success()
                ->body("{$reportRun->type->label()} finished for {$reportRun->device?->name}."),
            ReportRunStatus::NoData => Notification::make()
                ->title('Report finished with no data')
                ->warning()
                ->body("{$reportRun->type->label()} had no rows in the selected window."),
            ReportRunStatus::Failed => Notification::make()
                ->title('Report generation failed')
                ->danger()
                ->body($this->resolveFailureBody($reportRun)),
            default => null,
        };

        if ($notification === null) {
            return;
        }

        $actions = [
            Action::make('openReports')
                ->label('Open Reports')
                ->button()
                ->url(route('filament.admin.pages.reports'), shouldOpenInNewTab: true),
        ];

        if ($reportRun->status === ReportRunStatus::Completed && $reportRun->isDownloadable()) {
            array_unshift($actions, Action::make('downloadReport')
                ->label('Download')
                ->button()
                ->color('success')
                ->url(route('reporting.report-runs.download', ['reportRun' => $reportRun]), shouldOpenInNewTab: true));
        }

        $resolvedNotification = $notification->actions($actions);

        // Send immediately so queued job completion can show a toast popup regardless of current page.
        $recipient->notifyNow($resolvedNotification->toBroadcast());

        // Persist a bell notification entry as well.
        $recipient->notifyNow($resolvedNotification->toDatabase());

        // Trigger an immediate refresh of the database notifications modal/list over websockets.
        DatabaseNotificationsSent::dispatch($recipient);
    }

    private function resolveFailureBody(ReportRun $reportRun): string
    {
        $reason = is_string($reportRun->failure_reason) ? trim($reportRun->failure_reason) : '';

        if ($reason !== '') {
            return $reason;
        }

        return 'The report could not be generated. Open reports to retry with a different window.';
    }
}
