<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;

/**
 * An orphan model has no incoming relation, no outgoing relation and no
 * linked Filament resource or Livewire component.
 */
final class OrphanDetector
{
    /**
     * @return list<string> Sorted orphan model node ids.
     */
    public function detect(Graph $graph): array
    {
        $connected = [];

        foreach ($graph->edges as $edge) {
            if ($edge->type === EdgeType::ModelRelation) {
                $connected[$edge->source->value] = true;
                $connected[$edge->target->value] = true;
            }

            if (in_array($edge->type, [EdgeType::ResourceUsesModel, EdgeType::LivewireUsesModel], true)) {
                $connected[$edge->target->value] = true;
            }
        }

        $orphans = [];

        foreach ($graph->nodesOfType(NodeType::Model) as $node) {
            if (! isset($connected[$node->id->value])) {
                $orphans[] = $node->id->value;
            }
        }

        sort($orphans, SORT_STRING);

        return $orphans;
    }
}
