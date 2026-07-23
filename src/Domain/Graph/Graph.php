<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\NodeNotFoundException;

/**
 * Immutable directed graph.
 *
 * Nodes and edges keep the order given at construction time, which the
 * builder guarantees to be deterministic. Duplicate identifiers are ignored
 * after their first occurrence and edges pointing at unknown nodes are
 * dropped, so a graph instance is always internally consistent.
 */
final readonly class Graph
{
    /** @var list<Node> */
    public array $nodes;

    /** @var list<Edge> */
    public array $edges;

    /** @var array<string, Node> */
    private array $nodesById;

    /** @var array<string, Edge> */
    private array $edgesById;

    /** @var array<string, list<string>> */
    private array $outgoingEdgeIds;

    /** @var array<string, list<string>> */
    private array $incomingEdgeIds;

    /**
     * @param  list<Node>  $nodes
     * @param  list<Edge>  $edges
     */
    public function __construct(array $nodes, array $edges)
    {
        $nodesById = [];

        foreach ($nodes as $node) {
            if (! isset($nodesById[$node->id->value])) {
                $nodesById[$node->id->value] = $node;
            }
        }

        $edgesById = [];
        $outgoing = [];
        $incoming = [];

        foreach ($edges as $edge) {
            if (isset($edgesById[$edge->id->value])) {
                continue;
            }

            if (! isset($nodesById[$edge->source->value], $nodesById[$edge->target->value])) {
                continue;
            }

            $edgesById[$edge->id->value] = $edge;
            $outgoing[$edge->source->value][] = $edge->id->value;
            $incoming[$edge->target->value][] = $edge->id->value;
        }

        $this->nodes = array_values($nodesById);
        $this->edges = array_values($edgesById);
        $this->nodesById = $nodesById;
        $this->edgesById = $edgesById;
        $this->outgoingEdgeIds = $outgoing;
        $this->incomingEdgeIds = $incoming;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} $data */
        return new self(
            nodes: array_map(
                static fn (array $node): Node => Node::fromArray($node),
                $data['nodes'],
            ),
            edges: array_map(
                static fn (array $edge): Edge => Edge::fromArray($edge),
                $data['edges'],
            ),
        );
    }

    public function node(NodeId|string $id): ?Node
    {
        return $this->nodesById[$this->key($id)] ?? null;
    }

    public function nodeOrFail(NodeId|string $id): Node
    {
        return $this->node($id) ?? throw NodeNotFoundException::withId($this->key($id));
    }

    public function hasNode(NodeId|string $id): bool
    {
        return isset($this->nodesById[$this->key($id)]);
    }

    public function edge(EdgeId|string $id): ?Edge
    {
        $key = $id instanceof EdgeId ? $id->value : $id;

        return $this->edgesById[$key] ?? null;
    }

    /**
     * @return list<Edge>
     */
    public function outgoingEdges(NodeId|string $id): array
    {
        return $this->edgesFor($this->outgoingEdgeIds[$this->key($id)] ?? []);
    }

    /**
     * @return list<Edge>
     */
    public function incomingEdges(NodeId|string $id): array
    {
        return $this->edgesFor($this->incomingEdgeIds[$this->key($id)] ?? []);
    }

    /**
     * Distinct neighbour nodes, in edge declaration order.
     *
     * @return list<Node>
     */
    public function neighbours(NodeId|string $id): array
    {
        $nodeId = NodeId::fromString($this->key($id));
        $neighbours = [];

        foreach ([...$this->outgoingEdges($nodeId), ...$this->incomingEdges($nodeId)] as $edge) {
            $oppositeKey = $edge->opposite($nodeId)->value;

            if (! isset($neighbours[$oppositeKey]) && isset($this->nodesById[$oppositeKey])) {
                $neighbours[$oppositeKey] = $this->nodesById[$oppositeKey];
            }
        }

        return array_values($neighbours);
    }

    /**
     * @return list<Node>
     */
    public function nodesOfType(NodeType $type): array
    {
        return array_values(array_filter(
            $this->nodes,
            static fn (Node $node): bool => $node->type === $type,
        ));
    }

    /**
     * @return list<Edge>
     */
    public function edgesOfType(EdgeType $type): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (Edge $edge): bool => $edge->type === $type,
        ));
    }

    /**
     * Subgraph containing the given nodes and every edge whose two endpoints
     * are kept. Original ordering is preserved.
     *
     * @param  list<string>  $nodeIds
     * @param  list<string>|null  $edgeIds  When provided, only these edges are kept.
     */
    public function subgraph(array $nodeIds, ?array $edgeIds = null): self
    {
        $keptNodeIds = array_flip($nodeIds);
        $keptEdgeIds = $edgeIds === null ? null : array_flip($edgeIds);

        $nodes = array_values(array_filter(
            $this->nodes,
            static fn (Node $node): bool => isset($keptNodeIds[$node->id->value]),
        ));

        $edges = array_values(array_filter(
            $this->edges,
            static function (Edge $edge) use ($keptNodeIds, $keptEdgeIds): bool {
                if ($keptEdgeIds !== null && ! isset($keptEdgeIds[$edge->id->value])) {
                    return false;
                }

                return isset($keptNodeIds[$edge->source->value], $keptNodeIds[$edge->target->value]);
            },
        ));

        return new self($nodes, $edges);
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public function edgeCount(): int
    {
        return count($this->edges);
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(
                static fn (Node $node): array => $node->toArray(),
                $this->nodes,
            ),
            'edges' => array_map(
                static fn (Edge $edge): array => $edge->toArray(),
                $this->edges,
            ),
        ];
    }

    /**
     * @param  list<string>  $edgeIds
     * @return list<Edge>
     */
    private function edgesFor(array $edgeIds): array
    {
        $edges = [];

        foreach ($edgeIds as $edgeId) {
            if (isset($this->edgesById[$edgeId])) {
                $edges[] = $this->edgesById[$edgeId];
            }
        }

        return $edges;
    }

    private function key(NodeId|string $id): string
    {
        return $id instanceof NodeId ? $id->value : $id;
    }
}
