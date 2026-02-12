<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Automation\Models;

use App\Domain\Automation\Models\AutomationScheduleTrigger;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationScheduleTrigger>
 */
class AutomationScheduleTriggerFactory extends Factory
{
    protected $model = AutomationScheduleTrigger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workflow_version_id' => AutomationWorkflowVersion::factory(),
            'cron_expression' => '* * * * *',
            'timezone' => 'UTC',
            'next_run_at' => now()->addMinute(),
            'active' => true,
        ];
    }
}
