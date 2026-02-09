<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DataIngestion\Contracts\AnalyticsPublisher;
use App\Domain\DataIngestion\Models\IngestionMessage;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Laravel\Pennant\Feature;

class TelemetryAnalyticsPublishService
{
    public function __construct(
        private readonly AnalyticsPublisher $publisher,
    ) {}

    /**
     * @param  array<string, mixed>  $finalValues
     */
    public function publishTelemetry(Device $device, SchemaVersionTopic $topic, array $finalValues, IngestionMessage $ingestionMessage): void
    {
        if (! Feature::active('ingestion.pipeline.publish_analytics')) {
            return;
        }

        $this->publisher->publishTelemetry($device, $topic, $finalValues, $ingestionMessage);
    }

    /**
     * @param  array<string, mixed>  $validationErrors
     */
    public function publishInvalid(Device $device, SchemaVersionTopic $topic, array $validationErrors, IngestionMessage $ingestionMessage): void
    {
        if (! (bool) config('ingestion.publish_invalid_events', true)) {
            return;
        }

        $this->publisher->publishInvalid($device, $topic, $validationErrors, $ingestionMessage);
    }
}
