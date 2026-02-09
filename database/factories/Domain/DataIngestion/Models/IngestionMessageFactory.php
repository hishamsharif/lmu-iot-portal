<?php

declare(strict_types=1);

namespace Database\Factories\Domain\DataIngestion\Models;

use App\Domain\DataIngestion\Enums\IngestionStatus;
use App\Domain\DataIngestion\Models\IngestionMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IngestionMessage>
 */
class IngestionMessageFactory extends Factory
{
    protected $model = IngestionMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'organization_id' => null,
            'device_id' => null,
            'device_schema_version_id' => null,
            'schema_version_topic_id' => null,
            'source_subject' => 'devices.external-1.telemetry',
            'source_protocol' => 'mqtt',
            'source_message_id' => $this->faker->uuid(),
            'source_deduplication_key' => hash('sha256', $this->faker->unique()->uuid()),
            'raw_payload' => [
                'temp' => $this->faker->randomFloat(2, 10, 40),
            ],
            'error_summary' => null,
            'status' => $this->faker->randomElement(IngestionStatus::cases()),
            'received_at' => now(),
            'processed_at' => now(),
        ];
    }
}
