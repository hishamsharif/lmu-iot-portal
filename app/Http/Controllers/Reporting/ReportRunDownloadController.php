<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Actions\DownloadReportRunAction;
use App\Domain\Reporting\Exceptions\ReportingApiException;
use App\Domain\Reporting\Models\ReportRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportRunDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        ReportRun $reportRun,
        DownloadReportRunAction $downloadReportRunAction,
    ): Response {
        abort_unless($request->user()?->can('view', $reportRun), Response::HTTP_FORBIDDEN);

        try {
            $apiResponse = $downloadReportRunAction($reportRun);
        } catch (ReportingApiException $exception) {
            abort($exception->status > 0 ? $exception->status : Response::HTTP_BAD_GATEWAY, $exception->getMessage());
        }

        $contentType = (string) $apiResponse->header('Content-Type', 'text/csv; charset=UTF-8');
        $contentDisposition = (string) $apiResponse->header('Content-Disposition', "attachment; filename=\"report-{$reportRun->id}.csv\"");

        return response($apiResponse->body(), Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $contentDisposition,
        ]);
    }
}
