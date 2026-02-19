<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Models\ReportRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReportRunDestroyController extends Controller
{
    public function __invoke(Request $request, ReportRun $reportRun): Response
    {
        $organizationId = $request->integer('organization_id');

        abort_unless($organizationId > 0 && (int) $reportRun->organization_id === $organizationId, Response::HTTP_NOT_FOUND);

        if (
            is_string($reportRun->storage_disk)
            && trim($reportRun->storage_disk) !== ''
            && is_string($reportRun->storage_path)
            && trim($reportRun->storage_path) !== ''
            && Storage::disk($reportRun->storage_disk)->exists($reportRun->storage_path)
        ) {
            Storage::disk($reportRun->storage_disk)->delete($reportRun->storage_path);
        }

        $reportRun->delete();

        return response()->noContent();
    }
}
