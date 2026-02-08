<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\DevicePublishingSimulator;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisher;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\TelemetryIncoming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('simulates publishing and emits TelemetryIncoming events', function (): void {
    Event::fake([TelemetryIncoming::class]);

    $schemaVersion = DeviceSchemaVersion::factory()->create();

    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'label' => 'Telemetry',
        'suffix' => 'telemetry',
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'temp',
        'json_path' => 'temp',
        'type' => ParameterDataType::Integer,
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
        'external_id' => 'fan-01',
    ]);

    $published = [];

    $fakePublisher = new class($published) implements NatsPublisher
    {
        /** @var array<int, array{subject: string, payload: string}> */
        public array $published;

        public function __construct(array &$published)
        {
            $this->published = &$published;
        }

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

    /** @var DevicePublishingSimulator $simulator */
    $simulator = app(DevicePublishingSimulator::class);

    $simulator->simulate(
        device: $device,
        count: 3,
        intervalSeconds: 0,
        schemaVersionTopicId: $topic->id,
    );

    expect($published)->toHaveCount(3)
        ->and($published[0]['subject'])->toBe('devices.fan-01.telemetry');

    Event::assertDispatchedTimes(TelemetryIncoming::class, 3);
});
