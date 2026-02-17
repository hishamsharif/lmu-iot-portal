<?php

declare(strict_types=1);

namespace App\Http\Controllers\IoTDashboard;

use App\Domain\IoTDashboard\Application\WidgetRegistry;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IoTDashboardSnapshotsController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(
        Request $request,
        IoTDashboard $dashboard,
        WidgetRegistry $widgetRegistry,
    ) {
        Gate::authorize('view', $dashboard);

        $dashboard->loadMissing([
            'widgets' => fn ($query) => $query
                ->with([
                    'topic:id,label,suffix',
                    'device:id,uuid,name,organization_id',
                ])
                ->orderBy('sequence')
                ->orderBy('id'),
        ]);

        $widgetId = $request->integer('widget');

        $widgets = $dashboard->widgets
            ->when(
                $widgetId > 0,
                fn ($collection) => $collection->where('id', $widgetId),
            )
            ->values();

        $snapshots = $widgets
            ->map(function (IoTDashboardWidget $widget) use ($widgetRegistry): array {
                $definition = $widgetRegistry->forWidget($widget);
                $snapshot = $definition->resolveSnapshot($widget);

                return [
                    'id' => (int) $widget->id,
                    'type' => $widget->type,
                    ...$snapshot,
                ];
            })
            ->all();

        return response()->json([
            'version' => '2.0',
            'dashboard_id' => (int) $dashboard->id,
            'generated_at' => now()->toIso8601String(),
            'widgets' => $snapshots,
        ]);
    }
}
