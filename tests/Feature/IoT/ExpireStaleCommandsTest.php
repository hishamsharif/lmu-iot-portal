<?php

declare(strict_types=1);

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Events\CommandTimedOut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('marks stale command logs as timeout and broadcasts events', function (): void {
    Event::fake([CommandTimedOut::class]);

    $oldSent = DeviceCommandLog::factory()->sent()->create([
        'status' => CommandStatus::Sent,
        'sent_at' => now()->subMinutes(10),
        'error_message' => null,
    ]);

    $oldPending = DeviceCommandLog::factory()->create([
        'status' => CommandStatus::Pending,
        'sent_at' => null,
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    $recentSent = DeviceCommandLog::factory()->sent()->create([
        'status' => CommandStatus::Sent,
        'sent_at' => now()->subSeconds(10),
    ]);

    $this->artisan('iot:expire-stale-commands', ['--seconds' => 60])
        ->assertExitCode(0);

    $oldSent->refresh();
    $oldPending->refresh();
    $recentSent->refresh();

    expect($oldSent->status)->toBe(CommandStatus::Timeout)
        ->and($oldPending->status)->toBe(CommandStatus::Timeout)
        ->and($oldSent->error_message)->toBe('Command timed out waiting for device feedback.')
        ->and($recentSent->status)->toBe(CommandStatus::Sent);

    Event::assertDispatched(CommandTimedOut::class, 2);
});
