<?php

declare(strict_types=1);

use App\Domain\Automation\Data\WorkflowGraph;
use App\Domain\Automation\Services\WorkflowGraphValidator;

it('validates a graph with a trigger and no cycles', function (): void {
    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            ['id' => 'trigger', 'type' => 'telemetry-trigger'],
            ['id' => 'condition', 'type' => 'condition'],
            ['id' => 'action', 'type' => 'alert'],
        ],
        'edges' => [
            ['source' => 'trigger', 'target' => 'condition'],
            ['source' => 'condition', 'target' => 'action'],
        ],
    ]);

    expect(fn () => (new WorkflowGraphValidator)->validate($graph))->not->toThrow(RuntimeException::class);
    (new WorkflowGraphValidator)->validate($graph);

    expect(true)->toBeTrue();
});

it('fails when no trigger node exists', function (): void {
    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            ['id' => 'node-1', 'type' => 'condition'],
        ],
        'edges' => [],
    ]);

    expect(fn () => (new WorkflowGraphValidator)->validate($graph))
        ->toThrow(RuntimeException::class, 'requires at least one trigger node');
});

it('fails when a cycle exists', function (): void {
    $graph = WorkflowGraph::fromArray([
        'version' => 1,
        'nodes' => [
            ['id' => 'trigger', 'type' => 'telemetry-trigger'],
            ['id' => 'node-a', 'type' => 'condition'],
            ['id' => 'node-b', 'type' => 'alert'],
        ],
        'edges' => [
            ['source' => 'trigger', 'target' => 'node-a'],
            ['source' => 'node-a', 'target' => 'node-b'],
            ['source' => 'node-b', 'target' => 'node-a'],
        ],
    ]);

    expect(fn () => (new WorkflowGraphValidator)->validate($graph))
        ->toThrow(RuntimeException::class, 'cannot contain cycles');
});
