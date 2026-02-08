<?php

declare(strict_types=1);

use App\Events\TelemetryIncoming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('broadcasts telemetry incoming events', function (): void {
    Event::fake([TelemetryIncoming::class]);

    event(new TelemetryIncoming(
        topic: 'devices/fan/external-1/status',
        deviceUuid: 'uuid-1',
        deviceExternalId: 'external-1',
        payload: ['fan_speed' => 2],
    ));

    Event::assertDispatched(TelemetryIncoming::class);
});
