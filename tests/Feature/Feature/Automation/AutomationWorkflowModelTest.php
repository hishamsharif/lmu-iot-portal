<?php

declare(strict_types=1);

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts workflow status enum correctly', function (): void {
    $workflow = AutomationWorkflow::factory()->create([
        'status' => AutomationWorkflowStatus::Draft,
    ]);

    expect($workflow->status)
        ->toBe(AutomationWorkflowStatus::Draft);
});

it('relates workflow to versions and active version', function (): void {
    $workflow = AutomationWorkflow::factory()->create();
    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
        'version' => 2,
    ]);

    $workflow->update(['active_version_id' => $version->id]);
    $workflow->refresh();

    expect($workflow->versions)->toHaveCount(1)
        ->and($workflow->activeVersion?->is($version))->toBeTrue();
});
