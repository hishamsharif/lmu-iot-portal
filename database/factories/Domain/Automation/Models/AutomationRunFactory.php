<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Automation\Models;

use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRun>
 */
class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflow = AutomationWorkflow::factory();

        return [
            'organization_id' => Organization::factory(),
            'workflow_id' => $workflow,
            'workflow_version_id' => AutomationWorkflowVersion::factory()->for($workflow, 'workflow'),
            'trigger_type' => 'telemetry',
            'trigger_payload' => ['telemetry_log_id' => fake()->randomNumber()],
            'status' => AutomationRunStatus::Queued,
            'started_at' => null,
            'finished_at' => null,
            'error_summary' => null,
        ];
    }
}
