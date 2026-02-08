<?php

declare(strict_types=1);

use App\Domain\Shared\Models\User;
use App\Filament\Admin\Pages\TelemetryViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('can render the telemetry viewer page', function (): void {
    livewire(TelemetryViewer::class)
        ->assertSuccessful();
});

it('shows the live telemetry viewer content', function (): void {
    livewire(TelemetryViewer::class)
        ->assertSee('Pre-Ingestion Stream');
});

it('does not error when device query string is not a uuid', function (): void {
    $this->get('/admin/telemetry-viewer?device=1234567')
        ->assertOk()
        ->assertSee('Pre-Ingestion Stream');
});
