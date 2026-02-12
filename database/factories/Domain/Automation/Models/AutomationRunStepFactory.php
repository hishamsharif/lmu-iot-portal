<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Automation\Models;

use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationRunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRunStep>
 */
class AutomationRunStepFactory extends Factory
{
    protected $model = AutomationRunStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_run_id' => AutomationRun::factory(),
            'node_id' => 'node-'.fake()->randomNumber(5),
            'node_type' => fake()->randomElement(['condition', 'command', 'webhook', 'alert']),
            'status' => fake()->randomElement(['queued', 'running', 'completed']),
            'input_snapshot' => ['input' => fake()->word()],
            'output_snapshot' => ['output' => fake()->word()],
            'error' => null,
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
            'duration_ms' => fake()->numberBetween(5, 500),
        ];
    }
}
