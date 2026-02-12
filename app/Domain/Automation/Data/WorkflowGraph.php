<?php

declare(strict_types=1);

namespace App\Domain\Automation\Data;

class WorkflowGraph
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<string, mixed>  $viewport
     */
    public function __construct(
        public readonly int $version,
        public readonly array $nodes,
        public readonly array $edges,
        public readonly array $viewport = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $versionValue = $payload['version'] ?? 1;
        $version = is_numeric($versionValue) ? (int) $versionValue : 1;

        return new self(
            version: $version,
            nodes: self::normalizeStructuredList($payload['nodes'] ?? null),
            edges: self::normalizeStructuredList($payload['edges'] ?? null),
            viewport: self::normalizeAssociativeArray($payload['viewport'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'viewport' => $this->viewport,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeStructuredList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $resolvedItem = [];

            foreach ($item as $key => $itemValue) {
                if (is_string($key)) {
                    $resolvedItem[$key] = $itemValue;
                }
            }

            $resolved[] = $resolvedItem;
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeAssociativeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $key => $itemValue) {
            if (is_string($key)) {
                $resolved[$key] = $itemValue;
            }
        }

        return $resolved;
    }
}
