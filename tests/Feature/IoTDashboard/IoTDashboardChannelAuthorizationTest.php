<?php

declare(strict_types=1);

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authorizes private organization telemetry channels for organization members', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['is_super_admin' => false]);
    $user->organizations()->attach($organization->id);

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-iot-dashboard.organization.'.$organization->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

it('denies private organization telemetry channels for non-members', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-iot-dashboard.organization.'.$organization->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});
