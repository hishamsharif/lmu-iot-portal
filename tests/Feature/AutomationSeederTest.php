<?php

declare(strict_types=1);

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationTelemetryTrigger;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Database\Seeders\AutomationSeeder;
use Database\Seeders\DeviceControlSeeder;
use Database\Seeders\DeviceSchemaSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds an active energy meter power range automation that controls rgb colors', function (): void {
    $this->seed([
        OrganizationSeeder::class,
        DeviceSchemaSeeder::class,
        DeviceControlSeeder::class,
        AutomationSeeder::class,
    ]);

    $workflow = AutomationWorkflow::query()
        ->where('slug', 'energy-meter-power-l1-color-automation')
        ->first();

    expect($workflow)->not->toBeNull()
        ->and($workflow?->status)->toBe(AutomationWorkflowStatus::Active)
        ->and($workflow?->active_version_id)->not->toBeNull();

    $activeVersion = $workflow?->activeVersion;
    $graph = $activeVersion?->graph_json;

    expect($activeVersion)->not->toBeNull()
        ->and($graph)->toBeArray();

    $nodes = collect($graph['nodes'] ?? []);
    $commandPayloads = $nodes
        ->where('type', 'command')
        ->map(fn (array $node): array => (array) data_get($node, 'data.config.payload', []))
        ->values();

    expect($commandPayloads)->toHaveCount(3)
        ->and($commandPayloads->pluck('color_hex')->all())->toContain('#FFFF00')
        ->and($commandPayloads->pluck('color_hex')->all())->toContain('#800080')
        ->and($commandPayloads->pluck('color_hex')->all())->toContain('#0000FF');

    $commandPayloads->each(function (array $payload): void {
        expect($payload['power'] ?? null)->toBeTrue()
            ->and($payload['brightness'] ?? null)->toBe(100)
            ->and($payload['effect'] ?? null)->toBe('solid');
    });

    $energyMeter = Device::query()->where('external_id', 'main-energy-meter-01')->first();
    $energyTelemetryTopic = $energyMeter instanceof Device
        ? SchemaVersionTopic::query()
            ->where('device_schema_version_id', $energyMeter->device_schema_version_id)
            ->where('direction', TopicDirection::Publish->value)
            ->where('key', 'telemetry')
            ->first()
        : null;

    $trigger = AutomationTelemetryTrigger::query()
        ->where('workflow_version_id', $activeVersion?->id)
        ->first();

    expect($trigger)->not->toBeNull()
        ->and($energyMeter)->not->toBeNull()
        ->and($energyTelemetryTopic)->not->toBeNull()
        ->and($trigger?->device_id)->toBe($energyMeter?->id)
        ->and($trigger?->schema_version_topic_id)->toBe($energyTelemetryTopic?->id);
});
