<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Enums;

enum IngestionStage: string
{
    case Ingress = 'ingress';
    case Validate = 'validate';
    case Mutate = 'mutate';
    case Derive = 'derive';
    case Persist = 'persist';
    case Publish = 'publish';
}
