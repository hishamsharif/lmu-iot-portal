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

it('seeds an active energy meter current range automation that controls rgb colors', function (): void {
    $this->seed([
        OrganizationSeeder::class,
        DeviceSchemaSeeder::class,
        DeviceControlSeeder::class,
        AutomationSeeder::class,
    ]);

    $workflow = AutomationWorkflow::query()
        ->where('slug', 'energy-meter-current-a1-color-automation')
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

it('seeds an active energy consumption window query automation that triggers red blink command', function (): void {
    $this->seed([
        OrganizationSeeder::class,
        DeviceSchemaSeeder::class,
        DeviceControlSeeder::class,
        AutomationSeeder::class,
    ]);

    $workflow = AutomationWorkflow::query()
        ->where('slug', 'energy-meter-consumption-window-rgb-alert')
        ->first();

    expect($workflow)->not->toBeNull()
        ->and($workflow?->status)->toBe(AutomationWorkflowStatus::Active)
        ->and($workflow?->active_version_id)->not->toBeNull();

    $activeVersion = $workflow?->activeVersion;
    $graph = $activeVersion?->graph_json;

    expect($activeVersion)->not->toBeNull()
        ->and($graph)->toBeArray();

    $nodes = collect($graph['nodes'] ?? []);
    $queryNode = $nodes->first(fn (array $node): bool => ($node['type'] ?? null) === 'query');
    $conditionNode = $nodes->first(fn (array $node): bool => ($node['type'] ?? null) === 'condition');
    $commandNode = $nodes->first(fn (array $node): bool => ($node['type'] ?? null) === 'command');

    expect($queryNode)->toBeArray()
        ->and(data_get($queryNode, 'data.config.mode'))->toBe('sql')
        ->and(data_get($queryNode, 'data.config.window.size'))->toBe(15)
        ->and(data_get($queryNode, 'data.config.window.unit'))->toBe('minute')
        ->and(data_get($queryNode, 'data.config.sql'))->toBe('SELECT COALESCE(MAX(source_1.value) - MIN(source_1.value), 0) AS value FROM source_1');

    expect($conditionNode)->toBeArray()
        ->and(data_get($conditionNode, 'data.config.guided.left'))->toBe('query.value')
        ->and(data_get($conditionNode, 'data.config.guided.operator'))->toBe('>')
        ->and(data_get($conditionNode, 'data.config.guided.right'))->toBe(15);

    expect($commandNode)->toBeArray()
        ->and(data_get($commandNode, 'data.config.payload.power'))->toBeTrue()
        ->and(data_get($commandNode, 'data.config.payload.brightness'))->toBe(100)
        ->and(data_get($commandNode, 'data.config.payload.color_hex'))->toBe('#FF0000')
        ->and(data_get($commandNode, 'data.config.payload.effect'))->toBe('blink');
});
