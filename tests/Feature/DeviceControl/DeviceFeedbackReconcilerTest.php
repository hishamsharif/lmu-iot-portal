<?php

declare(strict_types=1);

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Models\DeviceDesiredTopicState;
use App\Domain\DeviceControl\Services\DeviceFeedbackReconciler;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Domain\DeviceSchema\Enums\TopicLinkType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\DeviceSchema\Models\SchemaVersionTopicLink;
use App\Events\CommandCompleted;
use App\Events\DeviceConnectionChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createDeviceWithLinkedFeedbackTopics(): array
{
    $version = DeviceSchemaVersion::factory()->create();

    $commandTopic = SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'control',
        'suffix' => 'control',
    ]);

    $stateTopic = SchemaVersionTopic::factory()->stateTopic()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'state',
        'suffix' => 'state',
    ]);

    SchemaVersionTopicLink::factory()->create([
        'from_schema_version_topic_id' => $commandTopic->id,
        'to_schema_version_topic_id' => $stateTopic->id,
        'link_type' => TopicLinkType::StateFeedback,
    ]);

    $deviceType = DeviceType::factory()->mqtt()->create([
        'protocol_config' => [
            'broker_host' => 'localhost',
            'broker_port' => 1883,
            'username' => null,
            'password' => null,
            'use_tls' => false,
            'base_topic' => 'devices',
        ],
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $version->id,
        'external_id' => 'light-01',
    ]);

    return [$device, $commandTopic, $stateTopic];
}

function bindFakeStateStore(): void
{
    $fake = new class implements NatsDeviceStateStore
    {
        /** @var array<string, array<string, array{topic: string, payload: array<string, mixed>, stored_at: string}>> */
        public array $states = [];

        public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void
        {
            $this->states[$deviceUuid][$topic] = [
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
            return array_values($this->states[$deviceUuid] ?? []);
        }

        public function getStateByTopic(string $deviceUuid, string $topic, string $host = '127.0.0.1', int $port = 4223): ?array
        {
            return $this->states[$deviceUuid][$topic] ?? null;
        }
    };

    app()->instance(NatsDeviceStateStore::class, $fake);
}

it('completes command logs using correlation id and reconciles desired topic state', function (): void {
    config(['broadcasting.default' => 'null']);
    Event::fake([CommandCompleted::class]);
    bindFakeStateStore();

    [$device, $commandTopic, $stateTopic] = createDeviceWithLinkedFeedbackTopics();

    $correlationId = (string) Str::uuid();

    $commandLog = DeviceCommandLog::factory()->sent()->create([
        'device_id' => $device->id,
        'schema_version_topic_id' => $commandTopic->id,
        'correlation_id' => $correlationId,
        'command_payload' => ['power' => 'on'],
    ]);

    DeviceDesiredTopicState::factory()->create([
        'device_id' => $device->id,
        'schema_version_topic_id' => $commandTopic->id,
        'desired_payload' => ['power' => 'on'],
        'correlation_id' => $correlationId,
        'reconciled_at' => null,
    ]);

    /** @var DeviceFeedbackReconciler $reconciler */
    $reconciler = app(DeviceFeedbackReconciler::class);

    $mqttTopic = $stateTopic->resolvedTopic($device);

    $result = $reconciler->reconcileInboundMessage($mqttTopic, [
        'power' => 'on',
        '_meta' => [
            'command_id' => $correlationId,
        ],
    ]);

    $commandLog->refresh();
    $desiredState = DeviceDesiredTopicState::query()
        ->where('device_id', $device->id)
        ->where('schema_version_topic_id', $commandTopic->id)
        ->first();

    expect($result)->not->toBeNull()
        ->and($result['command_log_id'])->toBe($commandLog->id)
        ->and($commandLog->status)->toBe(CommandStatus::Completed)
        ->and($commandLog->response_schema_version_topic_id)->toBe($stateTopic->id)
        ->and($desiredState?->reconciled_at)->not->toBeNull();

    Event::assertDispatched(CommandCompleted::class, fn (CommandCompleted $event): bool => $event->commandLog->id === $commandLog->id);
});

it('falls back to linked topic matching when command correlation id is absent', function (): void {
    config(['broadcasting.default' => 'null']);
    bindFakeStateStore();

    [$device, $commandTopic, $stateTopic] = createDeviceWithLinkedFeedbackTopics();

    $commandLog = DeviceCommandLog::factory()->sent()->create([
        'device_id' => $device->id,
        'schema_version_topic_id' => $commandTopic->id,
        'correlation_id' => null,
        'command_payload' => ['power' => 'off', 'mode' => 'manual'],
    ]);

    /** @var DeviceFeedbackReconciler $reconciler */
    $reconciler = app(DeviceFeedbackReconciler::class);

    $mqttTopic = $stateTopic->resolvedTopic($device);

    $result = $reconciler->reconcileInboundMessage($mqttTopic, [
        'power' => 'off',
        'mode' => 'manual',
    ]);

    $commandLog->refresh();

    expect($result)->not->toBeNull()
        ->and($result['command_log_id'])->toBe($commandLog->id)
        ->and($commandLog->status)->toBe(CommandStatus::Completed);
});

it('does not falsely complete a command when device state has no payload overlap', function (): void {
    config(['broadcasting.default' => 'null']);
    Event::fake([CommandCompleted::class]);
    bindFakeStateStore();

    [$device, $commandTopic, $stateTopic] = createDeviceWithLinkedFeedbackTopics();

    $commandLog = DeviceCommandLog::factory()->sent()->create([
        'device_id' => $device->id,
        'schema_version_topic_id' => $commandTopic->id,
        'correlation_id' => (string) Str::uuid(),
        'command_payload' => ['power' => true, 'brightness' => 80, 'color_hex' => '#00FF00'],
    ]);

    /** @var DeviceFeedbackReconciler $reconciler */
    $reconciler = app(DeviceFeedbackReconciler::class);

    $mqttTopic = $stateTopic->resolvedTopic($device);

    $result = $reconciler->reconcileInboundMessage($mqttTopic, [
        'power' => false,
        'brightness' => 40,
        'color_hex' => '#0000FF',
        'effect' => 'solid',
    ]);

    $commandLog->refresh();

    expect($result)->not->toBeNull()
        ->and($result['command_log_id'])->toBeNull()
        ->and($commandLog->status)->toBe(CommandStatus::Sent);

    Event::assertNotDispatched(CommandCompleted::class);
});

it('marks the device as online when a state message is reconciled', function (): void {
    config(['broadcasting.default' => 'null']);
    Event::fake([DeviceConnectionChanged::class]);
    bindFakeStateStore();

    [$device, $commandTopic, $stateTopic] = createDeviceWithLinkedFeedbackTopics();

    $device->updateQuietly(['connection_state' => 'offline', 'last_seen_at' => null]);

    /** @var DeviceFeedbackReconciler $reconciler */
    $reconciler = app(DeviceFeedbackReconciler::class);

    $mqttTopic = $stateTopic->resolvedTopic($device);

    $result = $reconciler->reconcileInboundMessage($mqttTopic, [
        'power' => true,
        'brightness' => 100,
    ]);

    $device->refresh();

    expect($result)->not->toBeNull()
        ->and($device->connection_state)->toBe('online')
        ->and($device->last_seen_at)->not->toBeNull();

    Event::assertDispatched(DeviceConnectionChanged::class, function (DeviceConnectionChanged $event) use ($device): bool {
        return $event->deviceId === $device->id && $event->connectionState === 'online';
    });
});

it('ignores internal mqtt bridge topics without changing device presence', function (): void {
    config(['broadcasting.default' => 'null']);
    Event::fake([DeviceConnectionChanged::class]);
    bindFakeStateStore();

    [$device] = createDeviceWithLinkedFeedbackTopics();

    $device->updateQuietly(['connection_state' => 'offline', 'last_seen_at' => null]);

    /** @var DeviceFeedbackReconciler $reconciler */
    $reconciler = app(DeviceFeedbackReconciler::class);

    $result = $reconciler->reconcileInboundMessage('$MQTT/JSA/example/internal/topic', [
        'stream' => '$MQTT_sess',
    ]);

    $device->refresh();

    expect($result)->toBeNull()
        ->and($device->connection_state)->toBe('offline')
        ->and($device->last_seen_at)->toBeNull();

    Event::assertNotDispatched(DeviceConnectionChanged::class);
});
