<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createFakeDeviceStateStore(?array $storedState = null): NatsDeviceStateStore
{
    return new class($storedState) implements NatsDeviceStateStore
    {
        /** @var array<string, array{topic: string, payload: array<string, mixed>, stored_at: string}> */
        public array $stored = [];

        /**
         * @param  array{topic: string, payload: array<string, mixed>, stored_at: string}|null  $initialState
         */
        public function __construct(?array $initialState = null)
        {
            if ($initialState !== null) {
                $deviceUuid = $initialState['device_uuid'] ?? 'default';
                unset($initialState['device_uuid']);
                $this->stored[$deviceUuid] = $initialState;
            }
        }

        public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void
        {
            $this->stored[$deviceUuid] = [
                'topic' => $topic,
                'payload' => $payload,
                'stored_at' => now()->toIso8601String(),
            ];
        }

        public function getLastState(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): ?array
        {
            return $this->stored[$deviceUuid] ?? null;
        }
    };
}

function bindFakeDeviceStateStore(?array $storedState = null): NatsDeviceStateStore
{
    $fake = createFakeDeviceStateStore($storedState);
    app()->instance(NatsDeviceStateStore::class, $fake);

    return $fake;
}

it('stores and retrieves device state', function (): void {
    $store = createFakeDeviceStateStore();

    $store->store('device-uuid-1', 'devices/light/status', ['brightness' => 75]);

    $state = $store->getLastState('device-uuid-1');

    expect($state)
        ->not->toBeNull()
        ->and($state['topic'])->toBe('devices/light/status')
        ->and($state['payload'])->toBe(['brightness' => 75])
        ->and($state['stored_at'])->not->toBeEmpty();
});

it('returns null for unknown device', function (): void {
    $store = createFakeDeviceStateStore();

    expect($store->getLastState('unknown-uuid'))->toBeNull();
});

it('overwrites state on subsequent stores', function (): void {
    $store = createFakeDeviceStateStore();

    $store->store('device-uuid-1', 'devices/light/status', ['brightness' => 50]);
    $store->store('device-uuid-1', 'devices/light/status', ['brightness' => 100]);

    $state = $store->getLastState('device-uuid-1');

    expect($state['payload'])->toBe(['brightness' => 100]);
});

it('stores state per device independently', function (): void {
    $store = createFakeDeviceStateStore();

    $store->store('device-1', 'devices/light-1/status', ['on' => true]);
    $store->store('device-2', 'devices/light-2/status', ['on' => false]);

    expect($store->getLastState('device-1')['payload'])->toBe(['on' => true])
        ->and($store->getLastState('device-2')['payload'])->toBe(['on' => false]);
});
