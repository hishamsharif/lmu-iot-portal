<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Database\Seeders\DeviceControlSeeder;
use Database\Seeders\DeviceSchemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a dimmable light device with state and control topics', function (): void {
    $this->seed(DeviceSchemaSeeder::class);
    $this->seed(DeviceControlSeeder::class);

    $deviceType = DeviceType::where('key', 'dimmable_light')->first();
    expect($deviceType)->not->toBeNull();

    $schema = DeviceSchema::where('device_type_id', $deviceType->id)->first();
    expect($schema)->not->toBeNull();

    $version = $schema?->versions()->where('status', 'active')->first();
    expect($version)->not->toBeNull();

    // Check publish topic (state)
    $stateTopic = SchemaVersionTopic::where('device_schema_version_id', $version->id)
        ->where('key', 'brightness_state')
        ->first();

    expect($stateTopic)->not->toBeNull()
        ->and($stateTopic?->direction)->toBe(TopicDirection::Publish)
        ->and($stateTopic?->suffix)->toBe('state');

    // Check subscribe topic (control)
    $controlTopic = SchemaVersionTopic::where('device_schema_version_id', $version->id)
        ->where('key', 'brightness_control')
        ->first();

    expect($controlTopic)->not->toBeNull()
        ->and($controlTopic?->direction)->toBe(TopicDirection::Subscribe)
        ->and($controlTopic?->suffix)->toBe('control');

    // Check parameter on control topic
    $parameter = ParameterDefinition::where('schema_version_topic_id', $controlTopic->id)
        ->where('key', 'brightness_level')
        ->first();

    expect($parameter)->not->toBeNull()
        ->and($parameter?->validation_rules)->toMatchArray(['min' => 0, 'max' => 10]);

    // Check device was created
    $device = Device::where('device_type_id', $deviceType->id)
        ->where('external_id', 'dimmable-light-01')
        ->first();

    expect($device)->not->toBeNull();
});

it('seeds the energy meter device required by automation workflows', function (): void {
    $this->seed(DeviceSchemaSeeder::class);
    $this->seed(DeviceControlSeeder::class);

    $energyMeterType = DeviceType::where('key', 'energy_meter')->first();
    expect($energyMeterType)->not->toBeNull();

    $energyMeter = Device::where('device_type_id', $energyMeterType->id)
        ->where('external_id', 'main-energy-meter-01')
        ->first();

    expect($energyMeter)->not->toBeNull();
});
