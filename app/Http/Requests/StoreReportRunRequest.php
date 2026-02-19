<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reporting\Enums\ReportGrouping;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Models\OrganizationReportSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRunRequest extends FormRequest
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
            'requested_by_user_id' => ['required', 'integer', 'exists:users,id'],
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'type' => ['required', Rule::enum(ReportType::class)],
            'grouping' => [
                'nullable',
                Rule::enum(ReportGrouping::class),
                Rule::requiredIf(fn (): bool => $this->isAggregationRequired()),
            ],
            'format' => ['nullable', 'string', Rule::in(['csv'])],
            'parameter_keys' => ['nullable', 'array'],
            'parameter_keys.*' => ['string', 'max:100'],
            'from_at' => ['required', 'date'],
            'until_at' => ['required', 'date', 'after:from_at'],
            'timezone' => ['required', 'timezone'],
            'payload' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'until_at.after' => 'The report end date/time must be after the start date/time.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fromAtInput = $this->input('from_at');
            $untilAtInput = $this->input('until_at');
            $organizationId = $this->integer('organization_id');

            if (! is_scalar($fromAtInput) || ! is_scalar($untilAtInput) || $organizationId <= 0) {
                return;
            }

            $fromAt = Carbon::parse((string) $fromAtInput);
            $untilAt = Carbon::parse((string) $untilAtInput);
            $configuredMaxDays = OrganizationReportSetting::query()
                ->where('organization_id', $organizationId)
                ->value('max_range_days');
            $maxRangeDays = is_numeric($configuredMaxDays)
                ? (int) $configuredMaxDays
                : (int) config('reporting.default_max_range_days', 31);
            $selectedDays = max(1, (int) ceil($fromAt->diffInSeconds($untilAt) / 86400));

            if ($selectedDays > $maxRangeDays) {
                $validator->errors()->add(
                    'until_at',
                    "The selected range exceeds the maximum allowed period of {$maxRangeDays} days.",
                );
            }

            if (
                $this->isAggregationRequired()
                && $this->input('grouping') === ReportGrouping::ShiftSchedule->value
            ) {
                $shiftSchedule = $this->normalizeShiftSchedule(data_get($this->input('payload'), 'shift_schedule'));

                if ($shiftSchedule === null) {
                    $validator->errors()->add(
                        'grouping',
                        'Shift schedule grouping requires a valid selected shift schedule in the payload.',
                    );

                    return;
                }

                if (! $this->hasNonOverlappingWindows($shiftSchedule['windows'])) {
                    $validator->errors()->add(
                        'grouping',
                        'Shift schedule grouping requires ordered windows without overlaps.',
                    );
                }
            }
        });
    }

    private function isAggregationRequired(): bool
    {
        $type = ReportType::tryFrom((string) $this->input('type'));

        return in_array($type, [ReportType::CounterConsumption, ReportType::StateUtilization], true);
    }

    /**
     * @return array{id: string, name: string, windows: array<int, array{id: string, name: string, start: string, end: string}>}|null
     */
    private function normalizeShiftSchedule(mixed $shiftSchedule): ?array
    {
        if (! is_array($shiftSchedule)) {
            return null;
        }

        $scheduleId = trim((string) ($shiftSchedule['id'] ?? ''));
        $scheduleName = trim((string) ($shiftSchedule['name'] ?? ''));
        $windows = $shiftSchedule['windows'] ?? null;

        if ($scheduleId === '' || $scheduleName === '' || ! is_array($windows) || $windows === []) {
            return null;
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

            if (
                $windowId === ''
                || $windowName === ''
                || preg_match('/^\d{2}:\d{2}$/', $start) !== 1
                || preg_match('/^\d{2}:\d{2}$/', $end) !== 1
            ) {
                continue;
            }

            $normalizedWindows[] = [
                'id' => $windowId,
                'name' => $windowName,
                'start' => $start,
                'end' => $end,
            ];
        }

        if ($normalizedWindows === []) {
            return null;
        }

        return [
            'id' => $scheduleId,
            'name' => $scheduleName,
            'windows' => $normalizedWindows,
        ];
    }

    /**
     * @param  array<int, array{id: string, name: string, start: string, end: string}>  $windows
     */
    private function hasNonOverlappingWindows(array $windows): bool
    {
        if ($windows === []) {
            return false;
        }

        $parsedWindows = [];
        $seenStarts = [];

        foreach ($windows as $window) {
            $startMinutes = $this->timeToMinutes($window['start']);
            $endMinutes = $this->timeToMinutes($window['end']);

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
}
