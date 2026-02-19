<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

enum ReportRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case NoData = 'no_data';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::NoData => 'No Data',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Running => 'warning',
            self::Completed => 'success',
            self::NoData => 'info',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::NoData, self::Failed, self::Cancelled], true);
    }
}
