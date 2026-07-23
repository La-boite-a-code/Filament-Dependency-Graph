<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Graph;

final readonly class GraphPath
{
    /**
     * @param  list<NodeId>  $nodes  Ordered from start to end.
     * @param  list<EdgeId>  $edges  Ordered edges connecting the nodes.
     */
    public function __construct(
        public array $nodes,
        public array $edges,
    ) {}

    /**
     * Number of edges in the path.
     */
    public function length(): int
    {
        return count($this->edges);
    }

    /**
     * @return array{nodes: list<string>, edges: list<string>}
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(
                static fn (NodeId $id): string => $id->value,
                $this->nodes,
            ),
            'edges' => array_map(
                static fn (EdgeId $id): string => $id->value,
                $this->edges,
            ),
        ];
    }
}
