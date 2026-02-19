<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Models\ReportRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReportRunDownloadController extends Controller
{
    public function __invoke(Request $request, ReportRun $reportRun): Response
    {
        $organizationId = $request->integer('organization_id');

        abort_unless($organizationId > 0 && (int) $reportRun->organization_id === $organizationId, Response::HTTP_NOT_FOUND);
        abort_unless($reportRun->status === ReportRunStatus::Completed, Response::HTTP_CONFLICT);
        abort_unless($reportRun->isDownloadable(), Response::HTTP_NOT_FOUND);

        $disk = (string) $reportRun->storage_disk;
        $path = (string) $reportRun->storage_path;

        abort_unless(Storage::disk($disk)->exists($path), Response::HTTP_NOT_FOUND);

        $downloadName = is_string($reportRun->file_name) && trim($reportRun->file_name) !== ''
            ? $reportRun->file_name
            : "report-{$reportRun->id}.csv";

        return Storage::disk($disk)->download(
            path: $path,
            name: $downloadName,
            headers: ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
