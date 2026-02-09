<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Enums;

enum IngestionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case FailedValidation = 'failed_validation';
    case InactiveSkipped = 'inactive_skipped';
    case FailedTerminal = 'failed_terminal';
    case Duplicate = 'duplicate';
}
