<?php

declare(strict_types=1);

use App\Console\Commands\IoT\IngestTelemetryCommand;
use Tests\TestCase;

uses(TestCase::class);

it('ignores nats internal subjects', function (): void {
    $command = new IngestTelemetryCommand;
    $method = new ReflectionMethod($command, 'shouldIgnoreSubject');

    expect($method->invoke($command, '$JS.API.STREAM.NAMES'))->toBeTrue()
        ->and($method->invoke($command, '$KV.device-states.some-device'))->toBeTrue()
        ->and($method->invoke($command, '_REQS.some-token.1'))->toBeTrue();
});

it('ignores analytics and invalid ingestion subjects to prevent loops', function (): void {
    $command = new IngestTelemetryCommand;
    $method = new ReflectionMethod($command, 'shouldIgnoreSubject');

    expect($method->invoke($command, 'iot.v1.analytics.local.1.device.telemetry'))->toBeTrue()
        ->and($method->invoke($command, 'iot.v1.invalid.local.1.validation'))->toBeTrue();
});

it('does not ignore regular telemetry subjects', function (): void {
    $command = new IngestTelemetryCommand;
    $method = new ReflectionMethod($command, 'shouldIgnoreSubject');

    expect($method->invoke($command, 'energy.main-energy-meter-01.telemetry'))->toBeFalse();
});
