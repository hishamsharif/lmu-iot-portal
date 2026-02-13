<?php

declare(strict_types=1);

use App\Http\Controllers\IoTDashboard\IoTDashboardWidgetLayoutController;
use App\Http\Controllers\IoTDashboard\IoTDashboardWidgetSeriesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')
    ->prefix('admin/iot-dashboard')
    ->name('admin.iot-dashboard.')
    ->group(function (): void {
        Route::get('/widgets/{widget}/series', IoTDashboardWidgetSeriesController::class)
            ->name('widgets.series');

        Route::post('/widgets/{widget}/layout', IoTDashboardWidgetLayoutController::class)
            ->name('widgets.layout');
    });
