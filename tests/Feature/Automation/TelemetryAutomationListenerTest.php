<?php

declare(strict_types=1);

use App\Domain\Automation\Contracts\TriggerMatcher;
use App\Domain\Automation\Jobs\StartAutomationRunFromTelemetry;
use App\Domain\Automation\Listeners\QueueTelemetryAutomationRuns;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Events\TelemetryReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues automation run jobs for matched workflow versions', function (): void {
    Queue::fake();

    $telemetryLog = DeviceTelemetryLog::factory()->create();

    app()->bind(TriggerMatcher::class, fn () => new class implements TriggerMatcher
    {
        public function matchTelemetryTriggers(DeviceTelemetryLog $telemetryLog): Collection
        {
            return collect([101, 202]);
        }
    });

    app(QueueTelemetryAutomationRuns::class)->handle(new TelemetryReceived($telemetryLog));

    Queue::assertPushed(StartAutomationRunFromTelemetry::class, 2);
    Queue::assertPushed(StartAutomationRunFromTelemetry::class, function (StartAutomationRunFromTelemetry $job) use ($telemetryLog): bool {
        return (string) $job->telemetryLogId === (string) $telemetryLog->getKey()
            && is_string($job->eventCorrelationId)
            && $job->eventCorrelationId !== '';
    });
});

it('does not queue automation runs when no workflow versions match', function (): void {
    Queue::fake();

    $telemetryLog = DeviceTelemetryLog::factory()->create();

    app()->bind(TriggerMatcher::class, fn () => new class implements TriggerMatcher
    {
        public function matchTelemetryTriggers(DeviceTelemetryLog $telemetryLog): Collection
        {
            return collect();
        }
    });

    app(QueueTelemetryAutomationRuns::class)->handle(new TelemetryReceived($telemetryLog));

    Queue::assertNothingPushed();
});

it('does not queue automation runs when automation pipeline is disabled', function (): void {
    Queue::fake();

    config()->set('automation.enabled', false);

    $telemetryLog = DeviceTelemetryLog::factory()->create();

    app()->bind(TriggerMatcher::class, fn () => new class implements TriggerMatcher
    {
        public function matchTelemetryTriggers(DeviceTelemetryLog $telemetryLog): Collection
        {
            return collect([101]);
        }
    });

    app(QueueTelemetryAutomationRuns::class)->handle(new TelemetryReceived($telemetryLog));

    Queue::assertNothingPushed();
});
