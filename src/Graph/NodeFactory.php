<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PageData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationManagerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

final class NodeFactory
{
    public function forPanel(PanelData $panel): Node
    {
        return new Node(
            id: NodeId::fromString(StableIdentifier::panel($panel->id)),
            type: NodeType::Panel,
            label: $panel->id,
            subtitle: $panel->domain ?? $panel->path,
            metadata: [
                'panel_id' => $panel->id,
                'path' => $panel->path,
                'domain' => $panel->domain,
                'resource_count' => count($panel->resourceIds),
                'resource_ids' => $panel->resourceIds,
            ],
            badges: [],
            status: DiscoveryStatus::Complete,
        );
    }

    /**
     * @param  list<string>  $badges
     */
    public function forResource(ResourceData $resource, array $badges = []): Node
    {
        return new Node(
            id: NodeId::fromString($resource->id),
            type: NodeType::Resource,
            label: $resource->shortName,
            subtitle: $resource->modelClass === '' ? null : ClassName::shortName($resource->modelClass),
            metadata: [
                'class' => $resource->class,
                'namespace' => ClassName::namespace($resource->class),
                'model_class' => $resource->modelClass,
                'model_id' => $resource->modelId,
                'label' => $resource->label,
                'plural_label' => $resource->pluralLabel,
                'navigation_group' => $resource->navigationGroup,
                'navigation_icon' => $resource->navigationIcon,
                'panel_ids' => $resource->panelIds,
                'pages' => array_map(
                    static fn (PageData $page): array => $page->toArray(),
                    $resource->pages,
                ),
                'relation_managers' => array_map(
                    static fn (RelationManagerData $manager): array => $manager->toArray(),
                    $resource->relationManagers,
                ),
                'warnings' => $resource->warnings,
            ],
            badges: $badges,
            status: $resource->status,
        );
    }

    /**
     * @param  list<string>  $badges
     */
    public function forModel(ModelData $model, array $badges = []): Node
    {
        return new Node(
            id: NodeId::fromString($model->id),
            type: NodeType::Model,
            label: $model->shortName,
            subtitle: $model->table,
            metadata: [
                'class' => $model->class,
                'namespace' => $model->namespace,
                'table' => $model->table,
                'connection' => $model->connection,
                'primary_key' => $model->primaryKey,
                'key_type' => $model->keyType,
                'incrementing' => $model->incrementing,
                'timestamps' => $model->timestamps,
                'soft_deletes' => $model->softDeletes,
                'traits' => $model->traits,
                'casts' => $model->casts,
                'fillable' => $model->fillable,
                'guarded' => $model->guarded,
                'hidden' => $model->hidden,
                'visible' => $model->visible,
                'application_owned' => $model->applicationOwned,
                'warnings' => $model->warnings,
            ],
            badges: $badges,
            status: $model->status,
        );
    }

    public function forLivewireComponent(LivewireComponentData $component): Node
    {
        $badges = [];

        if ($component->view !== null) {
            $badges[] = 'View';
        }

        if ($component->modelReferences !== []) {
            $badges[] = sprintf(
                '%d %s',
                count($component->modelReferences),
                count($component->modelReferences) === 1 ? 'model' : 'models',
            );
        }

        if ($component->status->isPartial()) {
            $badges[] = 'Partial';
        }

        return new Node(
            id: NodeId::fromString($component->id),
            type: NodeType::LivewireComponent,
            label: $component->shortName,
            subtitle: $component->alias,
            metadata: [
                'class' => $component->class,
                'namespace' => $component->namespace,
                'alias' => $component->alias,
                'view' => $component->view,
                'file' => $component->file,
                'public_properties' => $component->publicProperties,
                'public_methods' => $component->publicMethods,
                'model_ids' => $component->modelIds(),
                'model_references' => $component->modelReferences,
                'warnings' => $component->warnings,
            ],
            badges: $badges,
            status: $component->status,
        );
    }

    /**
     * One placeholder node represents the unresolved targets of a morphTo
     * relation, as a single polymorphic group.
     */
    public function forPolymorphicTarget(RelationData $relation): Node
    {
        return new Node(
            id: NodeId::fromString(self::polymorphicTargetId($relation)),
            type: NodeType::PolymorphicTarget,
            label: ucfirst($relation->method),
            subtitle: 'Polymorphic target',
            metadata: [
                'source_model_id' => $relation->sourceModelId,
                'method' => $relation->method,
                'morph_type' => $relation->morphType,
                'relation_id' => $relation->id,
            ],
            badges: ['Polymorphic'],
            status: $relation->status,
        );
    }

    public static function polymorphicTargetId(RelationData $relation): string
    {
        $normalizedSource = str_starts_with($relation->sourceModelId, 'model:')
            ? substr($relation->sourceModelId, strlen('model:'))
            : $relation->sourceModelId;

        return 'polymorphic:' . $normalizedSource . ':' . $relation->method;
    }
}
