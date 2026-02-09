<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Contracts;

use App\Domain\DataIngestion\Models\IngestionMessage;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;

interface AnalyticsPublisher
{
    /**
     * @param  array<string, mixed>  $finalValues
     */
    public function publishTelemetry(Device $device, SchemaVersionTopic $topic, array $finalValues, IngestionMessage $ingestionMessage): void;

    /**
     * @param  array<string, mixed>  $validationErrors
     */
    public function publishInvalid(Device $device, SchemaVersionTopic $topic, array $validationErrors, IngestionMessage $ingestionMessage): void;
}
