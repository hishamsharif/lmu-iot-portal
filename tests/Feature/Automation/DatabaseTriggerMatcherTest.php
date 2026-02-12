<?php

declare(strict_types=1);

use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Automation\Models\AutomationWorkflowVersion;
use App\Domain\Automation\Services\DatabaseTriggerMatcher;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns matching workflow version ids for telemetry context', function (): void {
    $telemetryLog = DeviceTelemetryLog::factory()->create();

    $workflow = AutomationWorkflow::factory()->create([
        'organization_id' => $telemetryLog->device->organization_id,
    ]);

    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
    ]);

    AutomationTelemetryTrigger::factory()
        ->forDevice($telemetryLog->device)
        ->create([
            'organization_id' => $telemetryLog->device->organization_id,
            'workflow_version_id' => $version->id,
            'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
        ]);

    $matched = app(DatabaseTriggerMatcher::class)->matchTelemetryTriggers($telemetryLog->load('device'));

    expect($matched->all())->toBe([$version->id]);
});
