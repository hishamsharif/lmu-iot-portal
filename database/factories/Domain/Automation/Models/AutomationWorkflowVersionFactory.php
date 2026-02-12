<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Automation\Models;

use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationWorkflowVersion>
 */
class AutomationWorkflowVersionFactory extends Factory
{
    protected $model = AutomationWorkflowVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_workflow_id' => AutomationWorkflow::factory(),
            'version' => 1,
            'graph_json' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'trigger', 'type' => 'telemetry-trigger', 'data' => []],
                    ['id' => 'action', 'type' => 'alert', 'data' => []],
                ],
                'edges' => [
                    ['id' => 'edge-1', 'source' => 'trigger', 'target' => 'action'],
                ],
                'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
            ],
            'graph_checksum' => hash('sha256', json_encode(['v' => 1], JSON_THROW_ON_ERROR)),
            'published_at' => null,
        ];
    }
}
