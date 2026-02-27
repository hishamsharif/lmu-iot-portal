<?php

declare(strict_types=1);

use App\Http\Controllers\IoTDashboard\IoTDashboardSnapshotsController;
use App\Http\Controllers\IoTDashboard\IoTDashboardWidgetLayoutController;
use App\Http\Controllers\Reporting\ReportRunDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')
    ->prefix('admin/iot-dashboard')
    ->name('admin.iot-dashboard.')
    ->group(function (): void {
        Route::get('/dashboards/{dashboard}/snapshots', IoTDashboardSnapshotsController::class)
            ->name('dashboards.snapshots');

        Route::post('/dashboards/{dashboard}/widgets/{widget}/layout', IoTDashboardWidgetLayoutController::class)
            ->name('dashboards.widgets.layout');
    });

Route::middleware('auth')
    ->prefix('admin/reports')
    ->name('reporting.')
    ->group(function (): void {
        Route::get('/report-runs/{reportRun}/download', ReportRunDownloadController::class)
            ->name('report-runs.download');
    });
