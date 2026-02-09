<?php

declare(strict_types=1);

namespace Database\Factories\Domain\DataIngestion\Models;

use App\Domain\DataIngestion\Enums\IngestionStage;
use App\Domain\DataIngestion\Enums\IngestionStatus;
use App\Domain\DataIngestion\Models\IngestionMessage;
use App\Domain\DataIngestion\Models\IngestionStageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestionStageLog>
 */
class IngestionStageLogFactory extends Factory
{
    protected $model = IngestionStageLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ingestion_message_id' => IngestionMessage::factory(),
            'stage' => $this->faker->randomElement(IngestionStage::cases()),
            'status' => $this->faker->randomElement(IngestionStatus::cases()),
            'duration_ms' => $this->faker->numberBetween(1, 2_000),
            'input_snapshot' => ['source' => 'test'],
            'output_snapshot' => ['result' => 'ok'],
            'change_set' => null,
            'errors' => null,
            'created_at' => now(),
        ];
    }
}
