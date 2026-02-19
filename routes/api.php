<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Internal\Reporting\OrganizationReportSettingsUpdateController;
use App\Http\Controllers\Api\Internal\Reporting\ReportRunDestroyController;
use App\Http\Controllers\Api\Internal\Reporting\ReportRunDownloadController;
use App\Http\Controllers\Api\Internal\Reporting\ReportRunStoreController;
use App\Http\Middleware\EnsureInternalReportingToken;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/reporting')
    ->middleware(EnsureInternalReportingToken::class)
    ->group(function (): void {
        Route::post('/report-runs', ReportRunStoreController::class);
        Route::delete('/report-runs/{reportRun}', ReportRunDestroyController::class);
        Route::get('/report-runs/{reportRun}/download', ReportRunDownloadController::class);
        Route::put('/organization-report-settings', OrganizationReportSettingsUpdateController::class);
    });
