<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrganizationReportSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'timezone' => ['required', 'timezone'],
            'max_range_days' => ['required', 'integer', 'min:1', 'max:366'],
            'shift_schedules' => ['nullable', 'array', 'max:12'],
            'shift_schedules.*.id' => ['nullable', 'string', 'max:100'],
            'shift_schedules.*.name' => ['required', 'string', 'max:100'],
            'shift_schedules.*.windows' => ['required', 'array', 'min:1', 'max:12'],
            'shift_schedules.*.windows.*.id' => ['nullable', 'string', 'max:100'],
            'shift_schedules.*.windows.*.name' => ['required', 'string', 'max:100'],
            'shift_schedules.*.windows.*.start' => ['required', 'date_format:H:i'],
            'shift_schedules.*.windows.*.end' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $shiftSchedules = $this->input('shift_schedules');

            if (! is_array($shiftSchedules)) {
                return;
            }

            $scheduleNames = [];

            foreach ($shiftSchedules as $scheduleIndex => $shiftSchedule) {
                if (! is_array($shiftSchedule)) {
                    continue;
                }

                $scheduleName = trim((string) ($shiftSchedule['name'] ?? ''));
                $windows = is_array($shiftSchedule['windows'] ?? null) ? $shiftSchedule['windows'] : [];

                if ($scheduleName !== '') {
                    $normalizedScheduleName = mb_strtolower($scheduleName);

                    if (in_array($normalizedScheduleName, $scheduleNames, true)) {
                        $validator->errors()->add(
                            "shift_schedules.{$scheduleIndex}.name",
                            'Shift schedule names must be unique.',
                        );
                    }

                    $scheduleNames[] = $normalizedScheduleName;
                }

                if ($windows === []) {
                    continue;
                }

                if (! $this->hasUniqueWindowNames($windows)) {
                    $validator->errors()->add(
                        "shift_schedules.{$scheduleIndex}.windows",
                        'Window names within a shift schedule must be unique.',
                    );
                }

                if (! $this->hasNonOverlappingWindows($windows)) {
                    $validator->errors()->add(
                        "shift_schedules.{$scheduleIndex}.windows",
                        'Shift schedule windows must be ordered without overlaps.',
                    );
                }
            }
        });
    }

    private function timeToMinutes(string $time): ?int
    {
        if (preg_match('/^(?<hour>\d{2}):(?<minute>\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches['hour'];
        $minute = (int) $matches['minute'];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }

    /**
     * @param  array<int, array{name?: mixed}>  $windows
     */
    private function hasUniqueWindowNames(array $windows): bool
    {
        $names = [];

        foreach ($windows as $window) {
            if (! is_array($window)) {
                continue;
            }

            $windowName = trim((string) ($window['name'] ?? ''));

            if ($windowName === '') {
                continue;
            }

            $normalized = mb_strtolower($windowName);

            if (in_array($normalized, $names, true)) {
                return false;
            }

            $names[] = $normalized;
        }

        return true;
    }

    /**
     * @param  array<int, array{start?: mixed, end?: mixed}>  $orderedShiftWindows
     */
    private function hasNonOverlappingWindows(array $orderedShiftWindows): bool
    {
        if ($orderedShiftWindows === []) {
            return true;
        }

        $parsedWindows = [];
        $seenStarts = [];

        foreach ($orderedShiftWindows as $shiftWindow) {
            if (! is_array($shiftWindow)) {
                return false;
            }

            $startMinutes = $this->timeToMinutes(trim((string) ($shiftWindow['start'] ?? '')));
            $endMinutes = $this->timeToMinutes(trim((string) ($shiftWindow['end'] ?? '')));

            if ($startMinutes === null || $endMinutes === null) {
                return false;
            }

            if (in_array($startMinutes, $seenStarts, true)) {
                return false;
            }

            $seenStarts[] = $startMinutes;

            $duration = $endMinutes - $startMinutes;

            if ($duration <= 0) {
                $duration += 1440;
            }

            if ($duration <= 0 || $duration > 1440) {
                return false;
            }

            $parsedWindows[] = [
                'start' => $startMinutes,
                'duration' => $duration,
            ];
        }

        $windowCount = count($parsedWindows);

        if ($windowCount <= 1) {
            return true;
        }

        for ($index = 0; $index < $windowCount; $index++) {
            $nextIndex = ($index + 1) % $windowCount;
            $start = $parsedWindows[$index]['start'];
            $nextStart = $parsedWindows[$nextIndex]['start'];
            $deltaToNextStart = $nextStart - $start;

            if ($deltaToNextStart <= 0) {
                $deltaToNextStart += 1440;
            }

            if ($parsedWindows[$index]['duration'] > $deltaToNextStart) {
                return false;
            }
        }

        return true;
    }
}
