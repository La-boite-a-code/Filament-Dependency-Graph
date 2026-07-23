<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Graph;

/**
 * Result of a focus traversal: the subgraph surrounding a root node and the
 * breadth-first depth at which each node was reached.
 */
final readonly class GraphNeighbourhood
{
    /**
     * @param  array<string, int>  $depths  Node id => depth from the root.
     */
    public function __construct(
        public NodeId $root,
        public Graph $graph,
        public array $depths,
    ) {}

    /**
     * @return array{root: string, depths: array<string, int>, graph: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    public function toArray(): array
    {
        return [
            'root' => $this->root->value,
            'depths' => $this->depths,
            'graph' => $this->graph->toArray(),
        ];
    }
}
