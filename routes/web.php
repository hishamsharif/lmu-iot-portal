<?php

declare(strict_types=1);

use App\Http\Controllers\IoTDashboard\IoTDashboardSnapshotsController;
use App\Http\Controllers\IoTDashboard\IoTDashboardWidgetLayoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')
    ->prefix('admin/iot-dashboard')
    ->name('admin.iot-dashboard.')
    ->group(function (): void {
        Route::get('/dashboards/{dashboard}/snapshots', IoTDashboardSnapshotsController::class)
            ->name('dashboards.snapshots');

        Route::post('/dashboards/{dashboard}/widgets/{widget}/layout', IoTDashboardWidgetLayoutController::class)
            ->name('dashboards.widgets.layout');
    });
