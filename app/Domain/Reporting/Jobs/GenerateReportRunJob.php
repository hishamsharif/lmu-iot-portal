<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Reporting\Services\ReportGenerationService;
use App\Domain\Reporting\Services\ReportRunNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public function __construct(
        public readonly int $reportRunId,
    ) {
        $connectionValue = config('reporting.queue_connection', config('queue.default', 'database'));
        $queueValue = config('reporting.queue', 'default');
        $connection = is_string($connectionValue) ? trim($connectionValue) : '';
        $queue = is_string($queueValue) ? trim($queueValue) : '';

        $this->onConnection($connection !== '' ? $connection : 'database');
        $this->onQueue($queue !== '' ? $queue : 'default');
    }

    public function handle(
        ReportGenerationService $reportGenerationService,
        ReportRunNotificationService $reportRunNotificationService,
    ): void {
        $reportRun = ReportRun::query()->find($this->reportRunId);

        if (! $reportRun instanceof ReportRun) {
            return;
        }

        if ($reportRun->status->isTerminal()) {
            return;
        }

        $reportRun->forceFill([
            'status' => ReportRunStatus::Running,
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        try {
            $reportRun = $reportGenerationService->generate($reportRun);
            $reportRunNotificationService->sendForStatus($reportRun);
        } catch (\Throwable $exception) {
            report($exception);

            $reportRun->forceFill([
                'status' => ReportRunStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => mb_strimwidth($exception->getMessage(), 0, 1000, '...'),
            ])->save();

            $reportRunNotificationService->sendForStatus($reportRun->refresh());
        }
    }
}
