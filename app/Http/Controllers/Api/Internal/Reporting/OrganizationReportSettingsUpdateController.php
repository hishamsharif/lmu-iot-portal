<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Models\OrganizationReportSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationReportSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OrganizationReportSettingsUpdateController extends Controller
{
    public function __invoke(UpdateOrganizationReportSettingsRequest $request): JsonResponse
    {
        $shiftSchedules = $this->normalizeShiftSchedules($request->input('shift_schedules'));

        $settings = OrganizationReportSetting::query()->updateOrCreate(
            ['organization_id' => $request->integer('organization_id')],
            [
                'timezone' => (string) $request->string('timezone'),
                'max_range_days' => $request->integer('max_range_days'),
                'shift_schedules' => $shiftSchedules,
            ],
        );

        return response()->json([
            'data' => [
                'id' => (int) $settings->id,
                'organization_id' => (int) $settings->organization_id,
                'timezone' => (string) $settings->timezone,
                'max_range_days' => (int) $settings->max_range_days,
                'shift_schedules' => $settings->shift_schedules ?? [],
            ],
        ]);
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     windows: array<int, array{id: string, name: string, start: string, end: string}>
     * }>
     */
    private function normalizeShiftSchedules(mixed $shiftSchedules): array
    {
        if (! is_array($shiftSchedules)) {
            return [];
        }

        $normalizedSchedules = [];

        foreach ($shiftSchedules as $shiftSchedule) {
            if (! is_array($shiftSchedule)) {
                continue;
            }

            $name = trim((string) ($shiftSchedule['name'] ?? ''));
            $scheduleId = trim((string) ($shiftSchedule['id'] ?? ''));
            $windows = is_array($shiftSchedule['windows'] ?? null) ? $shiftSchedule['windows'] : [];

            if ($name === '' || $windows === []) {
                continue;
            }

            $normalizedWindows = [];

            foreach ($windows as $window) {
                if (! is_array($window)) {
                    continue;
                }

                $windowId = trim((string) ($window['id'] ?? ''));
                $windowName = trim((string) ($window['name'] ?? ''));
                $start = trim((string) ($window['start'] ?? ''));
                $end = trim((string) ($window['end'] ?? ''));

                if ($windowName === '' || $start === '' || $end === '') {
                    continue;
                }

                $normalizedWindows[] = [
                    'id' => $windowId !== '' ? $windowId : (string) Str::ulid(),
                    'name' => $windowName,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            if ($normalizedWindows === []) {
                continue;
            }

            $normalizedSchedules[] = [
                'id' => $scheduleId !== '' ? $scheduleId : (string) Str::ulid(),
                'name' => $name,
                'windows' => $normalizedWindows,
            ];
        }

        return $normalizedSchedules;
    }
}
