<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Data\WorkflowGraph;
use Illuminate\Support\Arr;
use RuntimeException;

class WorkflowGraphValidator
{
    public function validate(WorkflowGraph $graph): void
    {
        $nodesById = [];
        foreach ($graph->nodes as $node) {
            $nodeId = Arr::get($node, 'id');
            if (! is_string($nodeId) || $nodeId === '') {
                throw new RuntimeException('Every node must have a non-empty string id.');
            }

            $nodesById[$nodeId] = $node;
        }

        if ($nodesById === []) {
            throw new RuntimeException('A workflow graph must include at least one node.');
        }

        $triggerNodes = array_filter($graph->nodes, static function (array $node): bool {
            return in_array(Arr::get($node, 'type'), ['telemetry-trigger', 'schedule-trigger'], true);
        });

        if ($triggerNodes === []) {
            throw new RuntimeException('A workflow graph requires at least one trigger node.');
        }

        $adjacency = [];
        foreach ($graph->edges as $edge) {
            $source = Arr::get($edge, 'source');
            $target = Arr::get($edge, 'target');

            if (! is_string($source) || ! is_string($target)) {
                throw new RuntimeException('Every edge must define string source and target node ids.');
            }

            if (! isset($nodesById[$source], $nodesById[$target])) {
                throw new RuntimeException('Edges must reference existing nodes.');
            }

            $adjacency[$source][] = $target;
        }

        $this->assertAcyclic(array_keys($nodesById), $adjacency);
    }

    /**
     * @param  array<int, string>  $nodeIds
     * @param  array<string, array<int, string>>  $adjacency
     */
    private function assertAcyclic(array $nodeIds, array $adjacency): void
    {
        $visiting = [];
        $visited = [];

        foreach ($nodeIds as $nodeId) {
            $this->visit($nodeId, $adjacency, $visiting, $visited);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $adjacency
     * @param  array<string, bool>  $visiting
     * @param  array<string, bool>  $visited
     */
    private function visit(string $nodeId, array $adjacency, array &$visiting, array &$visited): void
    {
        if (isset($visited[$nodeId])) {
            return;
        }

        if (isset($visiting[$nodeId])) {
            throw new RuntimeException('Workflow graph cannot contain cycles.');
        }

        $visiting[$nodeId] = true;

        foreach ($adjacency[$nodeId] ?? [] as $neighbor) {
            $this->visit($neighbor, $adjacency, $visiting, $visited);
        }

        unset($visiting[$nodeId]);
        $visited[$nodeId] = true;
    }
}
