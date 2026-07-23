<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;

/**
 * Detects cycles among model relation edges using Tarjan's strongly
 * connected components algorithm.
 *
 * Circular domain relationships are valid; cycle groups are reported as
 * information, never as application errors.
 */
final class CycleDetector
{
    /**
     * Returns cycle groups as sorted lists of node ids. A group is a cycle
     * when it contains more than one node, or one node with a self edge.
     *
     * @return list<list<string>>
     */
    public function detect(Graph $graph): array
    {
        $adjacency = [];
        $selfLoops = [];

        foreach ($graph->edgesOfType(EdgeType::ModelRelation) as $edge) {
            $source = $edge->source->value;
            $target = $edge->target->value;

            if ($source === $target) {
                $selfLoops[$source] = true;
            }

            $adjacency[$source][] = $target;
        }

        $index = 0;
        $indexes = [];
        $lowLinks = [];
        $onStack = [];
        $stack = [];
        $components = [];

        $strongConnect = function (string $node) use (
            &$strongConnect,
            &$index,
            &$indexes,
            &$lowLinks,
            &$onStack,
            &$stack,
            &$components,
            $adjacency,
        ): void {
            $indexes[$node] = $index;
            $lowLinks[$node] = $index;
            $index++;
            $stack[] = $node;
            $onStack[$node] = true;

            foreach ($adjacency[$node] ?? [] as $neighbour) {
                if (! isset($indexes[$neighbour])) {
                    $strongConnect($neighbour);
                    $lowLinks[$node] = min($lowLinks[$node], $lowLinks[$neighbour]);
                } elseif ($onStack[$neighbour] ?? false) {
                    $lowLinks[$node] = min($lowLinks[$node], $indexes[$neighbour]);
                }
            }

            if ($lowLinks[$node] === $indexes[$node]) {
                $component = [];

                do {
                    $member = array_pop($stack);

                    if ($member === null) {
                        break;
                    }

                    $onStack[$member] = false;
                    $component[] = $member;
                } while ($member !== $node);

                $components[] = $component;
            }
        };

        $nodeIds = array_keys($adjacency);

        foreach ($graph->edgesOfType(EdgeType::ModelRelation) as $edge) {
            $nodeIds[] = $edge->target->value;
        }

        $nodeIds = array_values(array_unique($nodeIds));
        sort($nodeIds, SORT_STRING);

        foreach ($nodeIds as $node) {
            if (! isset($indexes[$node])) {
                $strongConnect($node);
            }
        }

        $cycles = [];

        foreach ($components as $component) {
            if (count($component) > 1 || isset($selfLoops[$component[0]])) {
                sort($component, SORT_STRING);
                $cycles[] = $component;
            }
        }

        usort($cycles, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $cycles;
    }
}
