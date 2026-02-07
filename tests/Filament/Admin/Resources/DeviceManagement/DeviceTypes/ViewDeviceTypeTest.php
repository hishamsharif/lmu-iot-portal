<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\ViewDeviceType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the view device type page', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();

    livewire(ViewDeviceType::class, ['record' => $deviceType->id])
        ->assertSuccessful();
});

it('displays device type information', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create([
        'key' => 'mqtt_meter',
        'name' => 'MQTT Energy Meter',
    ]);

    livewire(ViewDeviceType::class, ['record' => $deviceType->id])
        ->assertSee('mqtt_meter')
        ->assertSee('MQTT Energy Meter');
});

it('displays MQTT protocol configuration', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();

    livewire(ViewDeviceType::class, ['record' => $deviceType->id])
        ->assertSee($deviceType->protocol_config->brokerHost);
});

it('displays HTTP protocol configuration', function (): void {
    $deviceType = DeviceType::factory()->http()->create();

    livewire(ViewDeviceType::class, ['record' => $deviceType->id])
        ->assertSee($deviceType->protocol_config->baseUrl);
});

it('can navigate to edit page from view page', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();

    livewire(ViewDeviceType::class, ['record' => $deviceType->id])
        ->assertActionExists('edit');
});
