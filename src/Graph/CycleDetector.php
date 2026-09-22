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
    /** @var array<string, list<string>> */
    private array $adjacency = [];

    private int $index = 0;

    /** @var array<string, int> */
    private array $indexes = [];

    /** @var array<string, int> */
    private array $lowLinks = [];

    /** @var array<string, bool> */
    private array $onStack = [];

    /** @var list<string> */
    private array $stack = [];

    /** @var list<list<string>> */
    private array $components = [];

    /**
     * Returns cycle groups as sorted lists of node ids. A group is a cycle
     * when it contains more than one node, or one node with a self edge.
     *
     * @return list<list<string>>
     */
    public function detect(Graph $graph): array
    {
        $this->adjacency = [];
        $this->index = 0;
        $this->indexes = [];
        $this->lowLinks = [];
        $this->onStack = [];
        $this->stack = [];
        $this->components = [];

        $selfLoops = [];
        $nodeIds = [];

        foreach ($graph->edgesOfType(EdgeType::ModelRelation) as $edge) {
            $source = $edge->source->value;
            $target = $edge->target->value;

            if ($source === $target) {
                $selfLoops[$source] = true;
            }

            $this->adjacency[$source][] = $target;
            $nodeIds[] = $source;
            $nodeIds[] = $target;
        }

        $nodeIds = array_values(array_unique($nodeIds));
        sort($nodeIds, SORT_STRING);

        foreach ($nodeIds as $node) {
            if (! isset($this->indexes[$node])) {
                $this->strongConnect($node);
            }
        }

        $cycles = [];

        foreach ($this->components as $component) {
            if (count($component) > 1 || isset($selfLoops[$component[0]])) {
                sort($component, SORT_STRING);
                $cycles[] = $component;
            }
        }

        usort($cycles, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $cycles;
    }

    private function strongConnect(string $node): void
    {
        $this->indexes[$node] = $this->index;
        $this->lowLinks[$node] = $this->index;
        $this->index++;
        $this->stack[] = $node;
        $this->onStack[$node] = true;

        foreach ($this->adjacency[$node] ?? [] as $neighbour) {
            if (! isset($this->indexes[$neighbour])) {
                $this->strongConnect($neighbour);
                $this->lowLinks[$node] = min($this->lowLinks[$node], $this->lowLinks[$neighbour]);
            } elseif ($this->onStack[$neighbour] ?? false) {
                $this->lowLinks[$node] = min($this->lowLinks[$node], $this->indexes[$neighbour]);
            }
        }

        if ($this->lowLinks[$node] !== $this->indexes[$node]) {
            return;
        }

        $component = [];

        do {
            $member = array_pop($this->stack);

            if ($member === null) {
                break;
            }

            $this->onStack[$member] = false;
            $component[] = $member;
        } while ($member !== $node);

        $this->components[] = $component;
    }
}
