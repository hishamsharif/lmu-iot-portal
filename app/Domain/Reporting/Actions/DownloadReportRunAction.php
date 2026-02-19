<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Reporting\Services\ReportingApiClient;
use Illuminate\Http\Client\Response;

class DownloadReportRunAction
{
    public function __construct(
        private readonly ReportingApiClient $reportingApiClient,
    ) {}

    public function __invoke(ReportRun $reportRun): Response
    {
        return $this->reportingApiClient->downloadReportRun(
            reportRunId: (int) $reportRun->id,
            organizationId: (int) $reportRun->organization_id,
        );
    }
}
