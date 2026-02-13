<?php

declare(strict_types=1);

namespace App\Http\Controllers\IoTDashboard;

use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\IoTDashboard\Services\LineChartWidgetDataResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IoTDashboardWidgetSeriesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        IoTDashboardWidget $widget,
        LineChartWidgetDataResolver $resolver,
    ): \Illuminate\Http\JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $widget->loadMissing(['dashboard', 'device:id,organization_id']);

        $organizationId = (int) $widget->dashboard->organization_id;
        $belongsToOrganization = $user->organizations()->whereKey($organizationId)->exists();

        if (! $user->isSuperAdmin() && ! $belongsToOrganization) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (
            is_numeric($widget->device_id)
            && (int) $widget->device?->organization_id !== $organizationId
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $lookbackMinutes = (int) min(max((int) $request->integer('lookback_minutes', $widget->lookback_minutes), 5), 1440);
        $maxPoints = (int) min(max((int) $request->integer('max_points', $widget->max_points), 20), 1000);

        return response()->json($resolver->resolve($widget, $lookbackMinutes, $maxPoints));
    }
}
