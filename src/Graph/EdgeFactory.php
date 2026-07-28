<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\EdgeId;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

final class EdgeFactory
{
    public function panelRegistersResource(PanelData $panel, string $resourceId): Edge
    {
        $panelNodeId = StableIdentifier::panel($panel->id);

        return new Edge(
            id: EdgeId::fromString(StableIdentifier::edge(
                EdgeType::PanelRegistersResource,
                $panelNodeId,
                $resourceId,
            )),
            source: NodeId::fromString($panelNodeId),
            target: NodeId::fromString($resourceId),
            type: EdgeType::PanelRegistersResource,
            label: 'registers',
            metadata: [
                'panel_id' => $panel->id,
            ],
            status: DiscoveryStatus::Complete,
        );
    }

    public function resourceUsesModel(ResourceData $resource, string $modelId): Edge
    {
        return new Edge(
            id: EdgeId::fromString(StableIdentifier::edge(
                EdgeType::ResourceUsesModel,
                $resource->id,
                $modelId,
            )),
            source: NodeId::fromString($resource->id),
            target: NodeId::fromString($modelId),
            type: EdgeType::ResourceUsesModel,
            label: 'model',
            metadata: [
                'resource_class' => $resource->class,
                'model_class' => $resource->modelClass,
            ],
            status: $resource->status,
        );
    }

    public function modelRelation(RelationData $relation, string $targetNodeId): Edge
    {
        return new Edge(
            id: EdgeId::fromString(StableIdentifier::edge(
                EdgeType::ModelRelation,
                $relation->sourceModelId,
                $targetNodeId,
                $relation->method,
            )),
            source: NodeId::fromString($relation->sourceModelId),
            target: NodeId::fromString($targetNodeId),
            type: EdgeType::ModelRelation,
            label: $relation->method,
            metadata: [
                'relation_id' => $relation->id,
                'method' => $relation->method,
                'relation_type' => $relation->type->value,
                'relation_label' => $relation->type->label(),
                'related_class' => $relation->relatedClass,
                'foreign_key' => $relation->foreignKey,
                'owner_key' => $relation->ownerKey,
                'local_key' => $relation->localKey,
                'pivot_table' => $relation->pivotTable,
                'morph_type' => $relation->morphType,
                'nullable' => $relation->nullable,
                'polymorphic' => $relation->polymorphic,
                'inverse_discovered' => $relation->inverseDiscovered,
                'warnings' => $relation->warnings,
            ],
            status: $relation->status,
        );
    }

    /**
     * @param  list<string>  $references
     */
    public function livewireUsesModel(
        LivewireComponentData $component,
        string $modelClass,
        array $references,
    ): Edge {
        $modelId = StableIdentifier::model($modelClass);

        return new Edge(
            id: EdgeId::fromString(StableIdentifier::edge(
                EdgeType::LivewireUsesModel,
                $component->id,
                $modelId,
            )),
            source: NodeId::fromString($component->id),
            target: NodeId::fromString($modelId),
            type: EdgeType::LivewireUsesModel,
            label: 'uses',
            metadata: [
                'component_class' => $component->class,
                'model_class' => $modelClass,
                'references' => $references,
            ],
            status: $component->status,
        );
    }
}
