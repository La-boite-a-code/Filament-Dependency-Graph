<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use Illuminate\Contracts\Config\Repository;
use LaBoiteACode\DependencyGraph\Contracts\GraphBuilder;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Graph\OrphanDetector;

/**
 * Builds the full graph from a snapshot, then applies a graph query:
 * relation type filters, panel filters, scope restriction, node type
 * filters, orphan visibility and focus traversal.
 */
final class BuildDependencyGraph
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly FocusDependencyGraph $focus,
        private readonly OrphanDetector $orphans,
        private readonly Repository $config,
    ) {}

    public function execute(ApplicationSnapshot $snapshot, ?GraphQuery $query = null): Graph
    {
        $graph = $this->builder->build($snapshot);

        if ($query === null) {
            return $graph;
        }

        $graph = $this->filterRelationTypes($graph, $query);
        $graph = $this->filterPanels($graph, $query);

        if ($query->scope === GraphScope::Filament) {
            $graph = $this->restrictToFilamentScope($graph);
        }

        $graph = $this->filterNodeTypes($graph, $query);

        if (! $query->includeOrphans) {
            $graph = $this->withoutOrphans($graph);
        }

        if ($query->hasFocus() && $query->focusNodeId !== null && $graph->hasNode($query->focusNodeId)) {
            $graph = $this->focus->execute(
                graph: $graph,
                nodeId: $query->focusNodeId,
                depth: $query->depth,
                direction: $query->direction,
            )->graph;
        }

        return $graph;
    }

    private function filterRelationTypes(Graph $graph, GraphQuery $query): Graph
    {
        if ($query->relationTypes === []) {
            return $graph;
        }

        $allowed = array_map(
            static fn (RelationType $type): string => $type->value,
            $query->relationTypes,
        );

        $edgeIds = [];

        foreach ($graph->edges as $edge) {
            if ($edge->type !== EdgeType::ModelRelation) {
                $edgeIds[] = $edge->id->value;

                continue;
            }

            if (in_array($edge->metadata['relation_type'] ?? null, $allowed, true)) {
                $edgeIds[] = $edge->id->value;
            }
        }

        return $graph->subgraph($this->allNodeIds($graph), $edgeIds);
    }

    private function filterPanels(Graph $graph, GraphQuery $query): Graph
    {
        if ($query->panelIds === []) {
            return $graph;
        }

        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if ($this->isVisibleForPanels($node, $query->panelIds)) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    /**
     * @param  list<string>  $panelIds
     */
    private function isVisibleForPanels(Node $node, array $panelIds): bool
    {
        if ($node->type === NodeType::Panel) {
            return in_array($node->metadata['panel_id'] ?? null, $panelIds, true);
        }

        if ($node->type === NodeType::Resource) {
            $resourcePanels = $node->metadata['panel_ids'] ?? [];

            return is_array($resourcePanels)
                && array_intersect($resourcePanels, $panelIds) !== [];
        }

        return true;
    }

    /**
     * The Filament scope starts from resource models and keeps related
     * models up to the configured depth. Panels and resources always stay.
     */
    private function restrictToFilamentScope(Graph $graph): Graph
    {
        $depth = (int) $this->config->get('filament-dependency-graph.graph.default_depth', 2);

        $keep = [];
        $roots = [];

        foreach ($graph->nodes as $node) {
            if ($node->type === NodeType::Panel || $node->type === NodeType::Resource) {
                $keep[$node->id->value] = true;
            }

            if ($node->type === NodeType::Resource) {
                $modelId = $node->metadata['model_id'] ?? null;

                if (is_string($modelId) && $graph->hasNode($modelId)) {
                    $roots[$modelId] = true;
                }
            }
        }

        /** @var list<array{0: string, 1: int}> $queue */
        $queue = [];
        $visited = [];

        foreach (array_keys($roots) as $rootId) {
            $keep[$rootId] = true;
            $visited[$rootId] = true;
            $queue[] = [$rootId, 0];
        }

        $cursor = 0;

        while ($cursor < count($queue)) {
            [$currentId, $currentDepth] = $queue[$cursor];
            $cursor++;

            if ($currentDepth >= $depth) {
                continue;
            }

            $edges = [...$graph->outgoingEdges($currentId), ...$graph->incomingEdges($currentId)];

            foreach ($edges as $edge) {
                if ($edge->type !== EdgeType::ModelRelation) {
                    continue;
                }

                $neighbour = $this->oppositeKey($edge, $currentId);
                $keep[$neighbour] = true;

                if (! isset($visited[$neighbour])) {
                    $visited[$neighbour] = true;
                    $queue[] = [$neighbour, $currentDepth + 1];
                }
            }
        }

        return $graph->subgraph(array_keys($keep));
    }

    private function filterNodeTypes(Graph $graph, GraphQuery $query): Graph
    {
        if ($query->nodeTypes === []) {
            return $graph;
        }

        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if (in_array($node->type, $query->nodeTypes, true)) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    private function withoutOrphans(Graph $graph): Graph
    {
        $orphans = array_flip($this->orphans->detect($graph));

        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if (! isset($orphans[$node->id->value])) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    /**
     * @return list<string>
     */
    private function allNodeIds(Graph $graph): array
    {
        return array_map(
            static fn (Node $node): string => $node->id->value,
            $graph->nodes,
        );
    }

    private function oppositeKey(Edge $edge, string $nodeId): string
    {
        return $edge->source->value === $nodeId ? $edge->target->value : $edge->source->value;
    }
}
