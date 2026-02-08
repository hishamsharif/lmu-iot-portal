<?php

declare(strict_types=1);

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Services\DeviceCommandDispatcher;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisher;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\CommandDispatched;
use App\Events\CommandSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function createDeviceWithSubscribeTopic(): array
{
    $schemaVersion = DeviceSchemaVersion::factory()->create();

    $subscribeTopic = SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'control',
        'label' => 'Control',
        'suffix' => 'control',
        'qos' => 2,
        'retain' => false,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $subscribeTopic->id,
        'key' => 'power',
        'json_path' => 'power',
        'type' => ParameterDataType::String,
        'default_value' => 'off',
        'required' => true,
        'is_critical' => false,
        'mutation_expression' => null,
        'sequence' => 1,
        'is_active' => true,
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
        'device_schema_version_id' => $schemaVersion->id,
        'external_id' => 'pump-42',
    ]);

    return [$device, $subscribeTopic];
}

function bindFakeNatsPublisher(): object
{
    $fakePublisher = new class implements NatsPublisher
    {
        /** @var array<int, array{subject: string, payload: string}> */
        public array $published = [];

        public function publish(string $subject, string $payload): void
        {
            $this->published[] = [
                'subject' => $subject,
                'payload' => $payload,
            ];
        }
    };

    $fakeFactory = new class($fakePublisher) implements NatsPublisherFactory
    {
        public function __construct(private NatsPublisher $publisher) {}

        public function make(string $host, int $port): NatsPublisher
        {
            return $this->publisher;
        }
    };

    app()->instance(NatsPublisherFactory::class, $fakeFactory);

    return $fakePublisher;
}

it('creates a command log with pending status and broadcasts CommandDispatched', function (): void {
    Event::fake([CommandDispatched::class, CommandSent::class]);

    [$device, $topic] = createDeviceWithSubscribeTopic();
    $fakePublisher = bindFakeNatsPublisher();

    /** @var DeviceCommandDispatcher $dispatcher */
    $dispatcher = app(DeviceCommandDispatcher::class);

    $commandLog = $dispatcher->dispatch(
        device: $device,
        topic: $topic,
        payload: ['power' => 'on'],
        userId: null,
    );

    expect($commandLog)->toBeInstanceOf(DeviceCommandLog::class)
        ->and($commandLog->device_id)->toBe($device->id)
        ->and($commandLog->schema_version_topic_id)->toBe($topic->id)
        ->and($commandLog->command_payload)->toBe(['power' => 'on']);

    Event::assertDispatched(CommandDispatched::class);
});

it('publishes to correct NATS subject and updates status to sent', function (): void {
    Event::fake([CommandDispatched::class, CommandSent::class]);

    [$device, $topic] = createDeviceWithSubscribeTopic();
    $fakePublisher = bindFakeNatsPublisher();

    /** @var DeviceCommandDispatcher $dispatcher */
    $dispatcher = app(DeviceCommandDispatcher::class);

    $commandLog = $dispatcher->dispatch(
        device: $device,
        topic: $topic,
        payload: ['power' => 'on'],
    );

    expect($fakePublisher->published)->toHaveCount(1)
        ->and($fakePublisher->published[0]['subject'])->toBe('devices.pump-42.control')
        ->and($commandLog->status)->toBe(CommandStatus::Sent)
        ->and($commandLog->sent_at)->not->toBeNull();

    Event::assertDispatched(CommandSent::class, function (CommandSent $event) use ($commandLog): bool {
        return $event->commandLog->id === $commandLog->id
            && $event->natsSubject === 'devices.pump-42.control';
    });
});

it('marks command as failed when NATS publish throws', function (): void {
    Event::fake([CommandDispatched::class, CommandSent::class]);

    [$device, $topic] = createDeviceWithSubscribeTopic();

    $failingPublisher = new class implements NatsPublisher
    {
        public function publish(string $subject, string $payload): void
        {
            throw new \RuntimeException('NATS connection refused');
        }
    };

    $failingFactory = new class($failingPublisher) implements NatsPublisherFactory
    {
        public function __construct(private NatsPublisher $publisher) {}

        public function make(string $host, int $port): NatsPublisher
        {
            return $this->publisher;
        }
    };

    app()->instance(NatsPublisherFactory::class, $failingFactory);

    /** @var DeviceCommandDispatcher $dispatcher */
    $dispatcher = app(DeviceCommandDispatcher::class);

    $commandLog = $dispatcher->dispatch(
        device: $device,
        topic: $topic,
        payload: ['power' => 'on'],
    );

    expect($commandLog->status)->toBe(CommandStatus::Failed)
        ->and($commandLog->error_message)->toBe('NATS connection refused');

    Event::assertDispatched(CommandDispatched::class);
    Event::assertNotDispatched(CommandSent::class);
});

it('stores the command log in the database', function (): void {
    Event::fake([CommandDispatched::class, CommandSent::class]);

    [$device, $topic] = createDeviceWithSubscribeTopic();
    bindFakeNatsPublisher();

    /** @var DeviceCommandDispatcher $dispatcher */
    $dispatcher = app(DeviceCommandDispatcher::class);

    $dispatcher->dispatch(
        device: $device,
        topic: $topic,
        payload: ['power' => 'on', 'mode' => 'auto'],
    );

    $this->assertDatabaseHas('device_command_logs', [
        'device_id' => $device->id,
        'schema_version_topic_id' => $topic->id,
        'status' => CommandStatus::Sent->value,
    ]);
});
