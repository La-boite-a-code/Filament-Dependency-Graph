<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Contracts\GraphBuilder;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;

/**
 * Builds the dependency graph from an application snapshot following the
 * documented order: panels, resources, models, structural edges, relation
 * edges, polymorphic placeholders, derived metadata, deterministic sorting.
 */
final class DefaultGraphBuilder implements GraphBuilder
{
    public function __construct(
        private readonly NodeFactory $nodes,
        private readonly EdgeFactory $edges,
        private readonly CycleDetector $cycles,
        private readonly OrphanDetector $orphans,
    ) {}

    public function build(ApplicationSnapshot $snapshot): Graph
    {
        $nodes = [];
        $edges = [];

        foreach ($snapshot->panels as $panel) {
            $node = $this->nodes->forPanel($panel);
            $nodes[$node->id->value] = $node;
        }

        $showPanelBadges = count($snapshot->panels) > 1;

        foreach ($snapshot->resources as $resource) {
            $node = $this->nodes->forResource(
                $resource,
                $showPanelBadges ? $resource->panelIds : [],
            );
            $nodes[$node->id->value] = $node;
        }

        $modelIdsWithResource = [];

        foreach ($snapshot->resources as $resource) {
            if ($resource->modelId !== null) {
                $modelIdsWithResource[$resource->modelId] = true;
            }
        }

        foreach ($snapshot->models as $model) {
            $node = $this->nodes->forModel($model);
            $nodes[$node->id->value] = $node;
        }

        foreach ($snapshot->panels as $panel) {
            foreach ($panel->resourceIds as $resourceId) {
                $edge = $this->edges->panelRegistersResource($panel, $resourceId);
                $edges[$edge->id->value] = $edge;
            }
        }

        foreach ($snapshot->resources as $resource) {
            if ($resource->modelId === null) {
                continue;
            }

            $edge = $this->edges->resourceUsesModel($resource, $resource->modelId);
            $edges[$edge->id->value] = $edge;
        }

        foreach ($snapshot->relations as $relation) {
            if ($relation->type === RelationType::MorphTo) {
                $placeholder = $this->nodes->forPolymorphicTarget($relation);

                if (! isset($nodes[$placeholder->id->value])) {
                    $nodes[$placeholder->id->value] = $placeholder;
                }

                $edge = $this->edges->modelRelation($relation, $placeholder->id->value);
                $edges[$edge->id->value] = $edge;

                continue;
            }

            if ($relation->targetModelId === null) {
                continue;
            }

            $edge = $this->edges->modelRelation($relation, $relation->targetModelId);
            $edges[$edge->id->value] = $edge;
        }

        $nodes = $this->applyDerivedBadges(
            $nodes,
            $snapshot,
            $modelIdsWithResource,
            new Graph(array_values($nodes), array_values($edges)),
        );

        return new Graph(
            $this->sortNodes(array_values($nodes)),
            $this->sortEdges(array_values($edges)),
        );
    }

    /**
     * @param  array<string, Node>  $nodes
     * @param  array<string, true>  $modelIdsWithResource
     * @return array<string, Node>
     */
    private function applyDerivedBadges(
        array $nodes,
        ApplicationSnapshot $snapshot,
        array $modelIdsWithResource,
        Graph $graph,
    ): array {
        $orphanIds = array_flip($this->orphans->detect($graph));

        $cycleIds = [];

        foreach ($this->cycles->detect($graph) as $cycle) {
            foreach ($cycle as $nodeId) {
                $cycleIds[$nodeId] = true;
            }
        }

        foreach ($snapshot->models as $model) {
            if (! isset($nodes[$model->id])) {
                continue;
            }

            $badges = $this->modelBadges(
                $model,
                hasResource: isset($modelIdsWithResource[$model->id]),
                isOrphan: isset($orphanIds[$model->id]),
                isInCycle: isset($cycleIds[$model->id]),
            );

            $nodes[$model->id] = $nodes[$model->id]->withBadges($badges);
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function modelBadges(ModelData $model, bool $hasResource, bool $isOrphan, bool $isInCycle): array
    {
        $badges = [$hasResource ? 'Resource' : 'No Resource'];

        if ($model->softDeletes) {
            $badges[] = 'SoftDeletes';
        }

        if (! $model->applicationOwned) {
            $badges[] = 'Vendor';
        }

        if ($model->status->isPartial()) {
            $badges[] = 'Partial';
        }

        if ($isOrphan) {
            $badges[] = 'Orphan';
        }

        if ($isInCycle) {
            $badges[] = 'Cycle';
        }

        return $badges;
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private function sortNodes(array $nodes): array
    {
        usort($nodes, static function (Node $a, Node $b): int {
            $namespaceA = $a->metadata['namespace'] ?? '';
            $namespaceB = $b->metadata['namespace'] ?? '';

            return [$a->type->sortPriority(), $namespaceA, $a->label, $a->id->value]
                <=> [$b->type->sortPriority(), $namespaceB, $b->label, $b->id->value];
        });

        return $nodes;
    }

    /**
     * @param  list<Edge>  $edges
     * @return list<Edge>
     */
    private function sortEdges(array $edges): array
    {
        usort($edges, static function (Edge $a, Edge $b): int {
            return [$a->source->value, $a->target->value, $a->type->value, $a->label]
                <=> [$b->source->value, $b->target->value, $b->type->value, $b->label];
        });

        return $edges;
    }
}
