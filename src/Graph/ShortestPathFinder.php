<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Graph\EdgeId;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\GraphPath;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;

/**
 * Unweighted shortest path search using breadth-first traversal.
 */
final class ShortestPathFinder
{
    public function find(
        Graph $graph,
        NodeId $from,
        NodeId $to,
        TraversalDirection $direction = TraversalDirection::Both,
    ): ?GraphPath {
        if (! $graph->hasNode($from) || ! $graph->hasNode($to)) {
            return null;
        }

        if ($from->equals($to)) {
            return new GraphPath(nodes: [$from], edges: []);
        }

        $visited = [$from->value => true];

        /** @var array<string, array{0: string, 1: string}> $previous  Node key => [previous node key, edge key] */
        $previous = [];

        /** @var list<string> $queue */
        $queue = [$from->value];
        $cursor = 0;

        while ($cursor < count($queue)) {
            $currentKey = $queue[$cursor];
            $cursor++;

            $currentId = NodeId::fromString($currentKey);

            $edges = [];

            if ($direction->includesOutgoing()) {
                $edges = $graph->outgoingEdges($currentKey);
            }

            if ($direction->includesIncoming()) {
                $edges = [...$edges, ...$graph->incomingEdges($currentKey)];
            }

            foreach ($edges as $edge) {
                $neighbourKey = $edge->opposite($currentId)->value;

                if (isset($visited[$neighbourKey])) {
                    continue;
                }

                $visited[$neighbourKey] = true;
                $previous[$neighbourKey] = [$currentKey, $edge->id->value];

                if ($neighbourKey === $to->value) {
                    return $this->reconstruct($from, $to, $previous);
                }

                $queue[] = $neighbourKey;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $previous
     */
    private function reconstruct(NodeId $from, NodeId $to, array $previous): GraphPath
    {
        $nodes = [$to->value];
        $edges = [];

        $current = $to->value;

        while ($current !== $from->value) {
            [$previousNode, $edge] = $previous[$current];
            $edges[] = $edge;
            $nodes[] = $previousNode;
            $current = $previousNode;
        }

        return new GraphPath(
            nodes: array_map(
                static fn (string $key): NodeId => NodeId::fromString($key),
                array_reverse($nodes),
            ),
            edges: array_map(
                static fn (string $key): EdgeId => EdgeId::fromString($key),
                array_reverse($edges),
            ),
        );
    }
}
