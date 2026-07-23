<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\GraphNeighbourhood;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;
use LaBoiteACode\DependencyGraph\Graph\GraphTraverser;

final class FocusDependencyGraph
{
    public function __construct(
        private readonly GraphTraverser $traverser,
    ) {}

    /**
     * @param  list<RelationType>  $relationTypes  Empty list keeps every relation type.
     */
    public function execute(
        Graph $graph,
        string $nodeId,
        ?int $depth = null,
        TraversalDirection $direction = TraversalDirection::Both,
        array $relationTypes = [],
        bool $includeResourceNodes = true,
        bool $includePanelNodes = true,
    ): GraphNeighbourhood {
        $allowedRelationTypes = array_map(
            static fn (RelationType $type): string => $type->value,
            $relationTypes,
        );

        $edgePredicate = static function (Edge $edge) use (
            $allowedRelationTypes,
            $includeResourceNodes,
            $includePanelNodes,
        ): bool {
            if ($edge->type === EdgeType::PanelRegistersResource) {
                return $includePanelNodes && $includeResourceNodes;
            }

            if ($edge->type === EdgeType::ResourceUsesModel) {
                return $includeResourceNodes;
            }

            if ($allowedRelationTypes === []) {
                return true;
            }

            return in_array($edge->metadata['relation_type'] ?? null, $allowedRelationTypes, true);
        };

        return $this->traverser->focus(
            graph: $graph,
            root: NodeId::fromString($nodeId),
            maxDepth: $depth,
            direction: $direction,
            edgePredicate: $edgePredicate,
        );
    }
}
