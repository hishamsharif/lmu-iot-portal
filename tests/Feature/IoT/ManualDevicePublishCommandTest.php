<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisher;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\DeviceStateReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function createDeviceForManualPublish(): Device
{
    $schemaVersion = DeviceSchemaVersion::factory()->create();

    SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'brightness_control',
        'label' => 'Brightness Control',
        'suffix' => 'control',
        'qos' => 1,
        'retain' => false,
    ]);

    $publishTopic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'brightness_status',
        'label' => 'Brightness Status',
        'suffix' => 'status',
        'qos' => 1,
        'retain' => true,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $publishTopic->id,
        'key' => 'brightness_level',
        'json_path' => 'brightness_level',
        'type' => ParameterDataType::Integer,
        'default_value' => 0,
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
            'base_topic' => 'devices/dimmable-light',
        ],
    ]);

    return Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'external_id' => 'dimmable-light-01',
        'name' => 'Lobby Dimmable Light',
    ]);
}

function bindFakeNatsForManualPublish(): void
{
    $fakePublisher = new class implements NatsPublisher
    {
        /** @var array<int, array{subject: string, payload: string}> */
        public array $published = [];

        public function publish(string $subject, string $payload): void
        {
            $this->published[] = ['subject' => $subject, 'payload' => $payload];
        }
    };

    $fakeFactory = new class($fakePublisher) implements NatsPublisherFactory
    {
        public function __construct(public NatsPublisher $publisher) {}

        public function make(string $host, int $port): NatsPublisher
        {
            return $this->publisher;
        }
    };

    app()->instance(NatsPublisherFactory::class, $fakeFactory);
}

it('fails with invalid device UUID', function (): void {
    $this->artisan('iot:manual-publish', ['device_uuid' => 'nonexistent-uuid'])
        ->assertExitCode(1);
});

it('fails when device has no publish topics', function (): void {
    $schemaVersion = DeviceSchemaVersion::factory()->create();

    SchemaVersionTopic::factory()->subscribe()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'control',
        'label' => 'Control',
        'suffix' => 'control',
    ]);

    $deviceType = DeviceType::factory()->mqtt()->create();

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    $this->artisan('iot:manual-publish', ['device_uuid' => $device->uuid])
        ->assertExitCode(1);
});

it('publishes device state and fires DeviceStateReceived event', function (): void {
    Event::fake([DeviceStateReceived::class]);
    bindFakeNatsForManualPublish();

    $device = createDeviceForManualPublish();
    $publishTopic = $device->schemaVersion->topics->firstWhere('suffix', 'status');

    $this->artisan('iot:manual-publish', ['device_uuid' => $device->uuid])
        ->expectsPromptsIntro('Manual State Publish — Lobby Dimmable Light')
        ->expectsChoice('Which publish topic?', (string) $publishTopic->id, [
            (string) $publishTopic->id => 'Brightness Status (status)',
        ])
        ->expectsQuestion('brightness_level (integer)', '7')
        ->expectsPromptsTable(
            headers: ['Property', 'Value'],
            rows: [
                ['Device', 'Lobby Dimmable Light'],
                ['MQTT Topic', 'devices/dimmable-light/dimmable-light-01/status'],
                ['NATS Subject', 'devices.dimmable-light.dimmable-light-01.status'],
                ['Payload', json_encode(['brightness_level' => 7], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
            ],
        )
        ->expectsPromptsOutro('Device state published successfully.')
        ->assertExitCode(0);

    Event::assertDispatched(DeviceStateReceived::class, function (DeviceStateReceived $event) use ($device): bool {
        return $event->deviceUuid === $device->uuid
            && $event->topic === 'devices/dimmable-light/dimmable-light-01/status'
            && $event->payload === ['brightness_level' => 7];
    });
})->skip((bool) env('PARATEST'), 'Interactive prompt tests are not compatible with parallel execution.');

it('uses external_id in topic resolution', function (): void {
    Event::fake([DeviceStateReceived::class]);
    bindFakeNatsForManualPublish();

    $device = createDeviceForManualPublish();
    $publishTopic = $device->schemaVersion->topics->firstWhere('suffix', 'status');

    $this->artisan('iot:manual-publish', ['device_uuid' => $device->uuid])
        ->expectsChoice('Which publish topic?', (string) $publishTopic->id, [
            (string) $publishTopic->id => 'Brightness Status (status)',
        ])
        ->expectsQuestion('brightness_level (integer)', '5')
        ->assertExitCode(0);

    Event::assertDispatched(DeviceStateReceived::class, function (DeviceStateReceived $event): bool {
        return str_contains($event->topic, 'dimmable-light-01')
            && $event->deviceExternalId === 'dimmable-light-01';
    });
})->skip((bool) env('PARATEST'), 'Interactive prompt tests are not compatible with parallel execution.');
