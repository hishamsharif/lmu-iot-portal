<?php

declare(strict_types=1);

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
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
        'status' => AutomationWorkflowStatus::Active,
    ]);

    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
    ]);

    $workflow->update(['active_version_id' => $version->id]);

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

it('supports strict, truthy, and missing_some operators in trigger filters', function (): void {
    $telemetryLog = DeviceTelemetryLog::factory()->create([
        'transformed_values' => [
            'status' => 'ok',
            'value' => 18.5,
        ],
    ]);

    $workflow = AutomationWorkflow::factory()->create([
        'organization_id' => $telemetryLog->device->organization_id,
        'status' => AutomationWorkflowStatus::Active,
    ]);

    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
    ]);

    $workflow->update(['active_version_id' => $version->id]);

    AutomationTelemetryTrigger::factory()
        ->forDevice($telemetryLog->device)
        ->create([
            'organization_id' => $telemetryLog->device->organization_id,
            'workflow_version_id' => $version->id,
            'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
            'filter_expression' => [
                'and' => [
                    [
                        '===' => [
                            ['var' => 'status'],
                            'ok',
                        ],
                    ],
                    [
                        '!!' => [
                            ['var' => 'value'],
                        ],
                    ],
                    [
                        '!' => [
                            [
                                'missing_some' => [
                                    2,
                                    ['status', 'value', 'payload.missing'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $matched = app(DatabaseTriggerMatcher::class)->matchTelemetryTriggers($telemetryLog->load('device'));

    expect($matched->all())->toBe([$version->id]);
});

it('does not match paused workflows', function (): void {
    $telemetryLog = DeviceTelemetryLog::factory()->create();

    $workflow = AutomationWorkflow::factory()->create([
        'organization_id' => $telemetryLog->device->organization_id,
        'status' => AutomationWorkflowStatus::Paused,
    ]);

    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
    ]);

    $workflow->update(['active_version_id' => $version->id]);

    AutomationTelemetryTrigger::factory()
        ->forDevice($telemetryLog->device)
        ->create([
            'organization_id' => $telemetryLog->device->organization_id,
            'workflow_version_id' => $version->id,
            'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
        ]);

    $matched = app(DatabaseTriggerMatcher::class)->matchTelemetryTriggers($telemetryLog->load('device'));

    expect($matched)->toBeEmpty();
});

it('invalidates cached trigger matches when workflow status changes', function (): void {
    $telemetryLog = DeviceTelemetryLog::factory()->create();

    $workflow = AutomationWorkflow::factory()->create([
        'organization_id' => $telemetryLog->device->organization_id,
        'status' => AutomationWorkflowStatus::Active,
    ]);

    $version = AutomationWorkflowVersion::factory()->create([
        'automation_workflow_id' => $workflow->id,
    ]);

    $workflow->update(['active_version_id' => $version->id]);

    AutomationTelemetryTrigger::factory()
        ->forDevice($telemetryLog->device)
        ->create([
            'organization_id' => $telemetryLog->device->organization_id,
            'workflow_version_id' => $version->id,
            'schema_version_topic_id' => $telemetryLog->schema_version_topic_id,
        ]);

    $matchedBeforePause = app(DatabaseTriggerMatcher::class)->matchTelemetryTriggers($telemetryLog->load('device'));
    expect($matchedBeforePause->all())->toBe([$version->id]);

    $workflow->update(['status' => AutomationWorkflowStatus::Paused]);

    $matchedAfterPause = app(DatabaseTriggerMatcher::class)->matchTelemetryTriggers($telemetryLog->load('device'));
    expect($matchedAfterPause)->toBeEmpty();
});
