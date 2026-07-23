<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\GraphNeighbourhood;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;

/**
 * Breadth-first focus traversal. Cycles are supported, output is stable and
 * the root node is always part of the result.
 */
final class GraphTraverser
{
    /**
     * @param  int|null  $maxDepth  Null means unlimited depth.
     * @param  (callable(Edge): bool)|null  $edgePredicate
     * @param  (callable(Node): bool)|null  $nodePredicate
     */
    public function focus(
        Graph $graph,
        NodeId $root,
        ?int $maxDepth = null,
        TraversalDirection $direction = TraversalDirection::Both,
        ?callable $edgePredicate = null,
        ?callable $nodePredicate = null,
    ): GraphNeighbourhood {
        $graph->nodeOrFail($root);

        $rootKey = $root->value;

        $includedNodes = [$rootKey => true];
        $includedEdges = [];
        $depths = [$rootKey => 0];
        $visited = [$rootKey => true];

        /** @var list<array{0: string, 1: int}> $queue */
        $queue = [[$rootKey, 0]];
        $cursor = 0;

        while ($cursor < count($queue)) {
            [$currentKey, $depth] = $queue[$cursor];
            $cursor++;

            if ($maxDepth !== null && $depth >= $maxDepth) {
                continue;
            }

            $currentId = NodeId::fromString($currentKey);

            foreach ($this->edgesFor($graph, $currentKey, $direction) as $edge) {
                if ($edgePredicate !== null && ! $edgePredicate($edge)) {
                    continue;
                }

                $neighbourKey = $edge->opposite($currentId)->value;
                $neighbourNode = $graph->node($neighbourKey);

                if ($neighbourNode === null) {
                    continue;
                }

                if ($nodePredicate !== null && ! $nodePredicate($neighbourNode)) {
                    continue;
                }

                $includedEdges[$edge->id->value] = true;

                if (! isset($includedNodes[$neighbourKey])) {
                    $includedNodes[$neighbourKey] = true;
                    $depths[$neighbourKey] = $depth + 1;
                }

                if (! isset($visited[$neighbourKey])) {
                    $visited[$neighbourKey] = true;
                    $queue[] = [$neighbourKey, $depth + 1];
                }
            }
        }

        return new GraphNeighbourhood(
            root: $root,
            graph: $graph->subgraph(array_keys($includedNodes), array_keys($includedEdges)),
            depths: $depths,
        );
    }

    /**
     * @return list<Edge>
     */
    private function edgesFor(Graph $graph, string $nodeKey, TraversalDirection $direction): array
    {
        $edges = [];

        if ($direction->includesOutgoing()) {
            $edges = $graph->outgoingEdges($nodeKey);
        }

        if ($direction->includesIncoming()) {
            foreach ($graph->incomingEdges($nodeKey) as $edge) {
                // A self loop appears in both lists but must be handled once.
                if (! $direction->includesOutgoing() || ! $edge->source->equals($edge->target)) {
                    $edges[] = $edge;
                }
            }
        }

        return $edges;
    }
}
