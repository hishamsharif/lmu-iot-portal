<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createFakeDeviceStateStore(?array $storedState = null): NatsDeviceStateStore
{
    return new class($storedState) implements NatsDeviceStateStore
    {
        /** @var array<string, array<string, array{topic: string, payload: array<string, mixed>, stored_at: string}>> */
        public array $storedByDevice = [];

        /**
         * @param  array{topic: string, payload: array<string, mixed>, stored_at: string}|null  $initialState
         */
        public function __construct(?array $initialState = null)
        {
            if ($initialState !== null) {
                $deviceUuid = $initialState['device_uuid'] ?? 'default';
                unset($initialState['device_uuid']);
                $topic = $initialState['topic'] ?? 'unknown/topic';
                $this->storedByDevice[$deviceUuid][$topic] = $initialState;
            }
        }

        public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void
        {
            $this->storedByDevice[$deviceUuid][$topic] = [
                'topic' => $topic,
                'payload' => $payload,
                'stored_at' => now()->toIso8601String(),
            ];
        }

        public function getLastState(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): ?array
        {
            $states = $this->getAllStates($deviceUuid, $host, $port);

            return $states[0] ?? null;
        }

        public function getAllStates(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): array
        {
            $states = array_values($this->storedByDevice[$deviceUuid] ?? []);

            usort($states, function (array $left, array $right): int {
                $leftTime = strtotime($left['stored_at']) ?: 0;
                $rightTime = strtotime($right['stored_at']) ?: 0;

                return $rightTime <=> $leftTime;
            });

            return $states;
        }

        public function getStateByTopic(string $deviceUuid, string $topic, string $host = '127.0.0.1', int $port = 4223): ?array
        {
            return $this->storedByDevice[$deviceUuid][$topic] ?? null;
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

it('retrieves all states and specific state by topic', function (): void {
    $store = createFakeDeviceStateStore();

    $store->store('device-1', 'devices/light-1/state', ['on' => true]);
    $store->store('device-1', 'devices/light-1/ack', ['accepted' => true]);

    $all = $store->getAllStates('device-1');
    $state = $store->getStateByTopic('device-1', 'devices/light-1/state');
    $ack = $store->getStateByTopic('device-1', 'devices/light-1/ack');

    expect($all)->toHaveCount(2)
        ->and($state['payload'])->toBe(['on' => true])
        ->and($ack['payload'])->toBe(['accepted' => true]);
});
