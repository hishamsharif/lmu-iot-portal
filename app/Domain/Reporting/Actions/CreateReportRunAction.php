<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Reporting\Services\ReportingApiClient;
use App\Domain\Shared\Models\User;
use RuntimeException;

class CreateReportRunAction
{
    public function __construct(
        private readonly ReportingApiClient $reportingApiClient,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(User $user, array $payload): ReportRun
    {
        $response = $this->reportingApiClient->createReportRun([
            ...$payload,
            'requested_by_user_id' => $user->id,
        ]);

        $reportRunId = (int) data_get($response, 'data.id');
        $reportRun = ReportRun::query()->find($reportRunId);

        if (! $reportRun instanceof ReportRun) {
            throw new RuntimeException('Report run creation failed: invalid API response.');
        }

        return $reportRun;
    }
}
