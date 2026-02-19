<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use Carbon\CarbonImmutable;

class ShiftWindowResolver
{
    /**
     * @return array{
     *     from_utc: CarbonImmutable,
     *     until_utc: CarbonImmutable,
     *     from_local: CarbonImmutable,
     *     until_local: CarbonImmutable,
     * }
     */
    public function normalizeRange(
        \DateTimeInterface $fromAt,
        \DateTimeInterface $untilAt,
        string $timezone,
    ): array {
        $resolvedTimezone = $this->resolveTimezone($timezone);

        $fromLocal = CarbonImmutable::instance($fromAt)->setTimezone($resolvedTimezone);
        $untilLocal = CarbonImmutable::instance($untilAt)->setTimezone($resolvedTimezone);

        if ($untilLocal->lessThanOrEqualTo($fromLocal)) {
            $untilLocal = $fromLocal->addMinute();
        }

        return [
            'from_utc' => $fromLocal->utc(),
            'until_utc' => $untilLocal->utc(),
            'from_local' => $fromLocal,
            'until_local' => $untilLocal,
        ];
    }

    public function inShiftWindow(
        \DateTimeInterface $timestampUtc,
        string $timezone,
        ?string $shiftStart,
        ?string $shiftEnd,
    ): bool {
        $resolvedShift = $this->resolveShiftTimes($shiftStart, $shiftEnd);

        if ($resolvedShift === null) {
            return true;
        }

        $localTimestamp = CarbonImmutable::instance($timestampUtc)->setTimezone($this->resolveTimezone($timezone));
        $timeOfDay = $localTimestamp->format('H:i');
        $start = $resolvedShift['start'];
        $end = $resolvedShift['end'];

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $timeOfDay >= $start && $timeOfDay < $end;
        }

        return $timeOfDay >= $start || $timeOfDay < $end;
    }

    public function resolveTimezone(string $timezone): string
    {
        $trimmed = trim($timezone);
        $configuredTimezone = config('app.timezone', 'UTC');
        $fallbackTimezone = is_string($configuredTimezone) ? $configuredTimezone : 'UTC';

        return in_array($trimmed, timezone_identifiers_list(), true)
            ? $trimmed
            : $fallbackTimezone;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    public function resolveShiftTimes(?string $shiftStart, ?string $shiftEnd): ?array
    {
        if (! is_string($shiftStart) || ! is_string($shiftEnd)) {
            return null;
        }

        if (! $this->isValidShiftTime($shiftStart) || ! $this->isValidShiftTime($shiftEnd)) {
            return null;
        }

        return [
            'start' => $shiftStart,
            'end' => $shiftEnd,
        ];
    }

    private function isValidShiftTime(?string $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1;
    }
}
