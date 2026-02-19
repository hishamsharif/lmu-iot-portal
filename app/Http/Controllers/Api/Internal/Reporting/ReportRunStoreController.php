<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\Reporting\Enums\ReportGrouping;
use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportRunJob;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Shared\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRunRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReportRunStoreController extends Controller
{
    public function __invoke(StoreReportRunRequest $request): JsonResponse
    {
        $organizationId = $request->integer('organization_id');
        $requestedByUserId = $request->integer('requested_by_user_id');
        $deviceId = $request->integer('device_id');

        $requestedBy = User::query()->find($requestedByUserId);

        if (! $requestedBy instanceof User) {
            throw ValidationException::withMessages([
                'requested_by_user_id' => 'The selected requesting user is invalid.',
            ]);
        }

        if (! $requestedBy->isSuperAdmin() && ! $requestedBy->organizations()->whereKey($organizationId)->exists()) {
            throw ValidationException::withMessages([
                'organization_id' => 'The requesting user does not have access to this organization.',
            ]);
        }

        $device = Device::query()
            ->whereKey($deviceId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $device instanceof Device) {
            throw ValidationException::withMessages([
                'device_id' => 'The selected device is invalid for the chosen organization.',
            ]);
        }

        $type = ReportType::from((string) $request->string('type'));
        $groupingInput = $request->input('grouping');
        $isAggregationType = in_array($type, [ReportType::CounterConsumption, ReportType::StateUtilization], true);
        $grouping = $isAggregationType && is_string($groupingInput) && trim($groupingInput) !== ''
            ? ReportGrouping::from($groupingInput)
            : null;

        $reportRun = ReportRun::query()->create([
            'organization_id' => $organizationId,
            'device_id' => $device->id,
            'requested_by_user_id' => $requestedBy->id,
            'type' => $type,
            'status' => ReportRunStatus::Queued,
            'format' => (string) $request->input('format', 'csv'),
            'grouping' => $grouping,
            'parameter_keys' => is_array($request->input('parameter_keys')) ? array_values($request->input('parameter_keys')) : [],
            'from_at' => $request->date('from_at'),
            'until_at' => $request->date('until_at'),
            'timezone' => (string) $request->string('timezone'),
            'payload' => is_array($request->input('payload')) ? $request->input('payload') : null,
        ]);

        GenerateReportRunJob::dispatch((int) $reportRun->id);

        $reportRun->loadMissing(['organization:id,name', 'device:id,name,external_id', 'requestedBy:id,name']);

        return response()->json([
            'data' => [
                'id' => (int) $reportRun->id,
                'organization_id' => (int) $reportRun->organization_id,
                'device_id' => (int) $reportRun->device_id,
                'status' => $reportRun->status->value,
                'type' => $reportRun->type->value,
                'created_at' => $reportRun->created_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }
}
