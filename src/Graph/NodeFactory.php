<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerActionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchableData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\EventData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\FormRequestData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ListenerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\PolicyData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
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

    public function forRoute(RouteData $route): Node
    {
        $badges = $route->actionType === RouteData::ACTION_CONTROLLER ? [] : [ucfirst($route->actionType)];

        if ($route->status->isPartial()) {
            $badges[] = 'Partial';
        }

        return new Node(
            id: NodeId::fromString($route->id),
            type: NodeType::Route,
            label: $route->label(),
            subtitle: $route->name,
            metadata: [
                'uri' => $route->uri,
                'methods' => $route->methods,
                'name' => $route->name,
                'domain' => $route->domain,
                'action_type' => $route->actionType,
                'controller_class' => $route->controllerClass,
                'controller_method' => $route->controllerMethod,
                'livewire_class' => $route->livewireClass,
                'view' => $route->view,
                'middleware' => $route->middleware,
                'resolved_middleware' => $route->resolvedMiddleware,
                'bound_parameters' => $route->boundParameters,
                'file' => $route->file,
                'warnings' => $route->warnings,
            ],
            badges: $badges,
            status: $route->status,
        );
    }

    public function forController(ControllerData $controller): Node
    {
        $count = count($controller->actions);
        $badges = [sprintf('%d %s', $count, $count === 1 ? 'action' : 'actions')];

        if ($controller->status->isPartial()) {
            $badges[] = 'Partial';
        }

        return new Node(
            id: NodeId::fromString($controller->id),
            type: NodeType::Controller,
            label: ClassName::shortName($controller->class),
            subtitle: null,
            metadata: [
                'class' => $controller->class,
                'namespace' => ClassName::namespace($controller->class),
                'file' => $controller->file,
                'actions' => array_map(
                    static fn (ControllerActionData $action): array => $action->toArray(),
                    $controller->actions,
                ),
                'warnings' => $controller->warnings,
            ],
            badges: $badges,
            status: $controller->status,
        );
    }

    public function forFormRequest(FormRequestData $request): Node
    {
        $badges = $request->rules === null ? [] : ['Rules'];

        if ($request->status->isPartial()) {
            $badges[] = 'Partial';
        }

        return new Node(
            id: NodeId::fromString($request->id),
            type: NodeType::FormRequest,
            label: ClassName::shortName($request->class),
            subtitle: null,
            metadata: [
                'class' => $request->class,
                'namespace' => ClassName::namespace($request->class),
                'file' => $request->file,
                'has_authorize' => $request->hasAuthorize,
                'has_rules' => $request->hasRules,
                'rules' => $request->rules,
                'warnings' => $request->warnings,
            ],
            badges: $badges,
            status: $request->status,
        );
    }

    /**
     * @param  list<string>  $modelClasses  Every model the policy guards; defaults to the policy's own model.
     */
    public function forPolicy(PolicyData $policy, array $modelClasses = []): Node
    {
        $modelClasses = $modelClasses === [] ? [$policy->modelClass] : $modelClasses;

        return new Node(
            id: NodeId::fromString($policy->id),
            type: NodeType::Policy,
            label: ClassName::shortName($policy->class),
            subtitle: implode(', ', array_map(static fn (string $class): string => ClassName::shortName($class), $modelClasses)),
            metadata: [
                'class' => $policy->class,
                'namespace' => ClassName::namespace($policy->class),
                'file' => $policy->file,
                'model_classes' => $modelClasses,
                'abilities' => $policy->abilities,
                'source' => $policy->source,
                'warnings' => $policy->warnings,
            ],
            badges: [ucfirst($policy->source)],
            status: $policy->status,
        );
    }

    /**
     * @param  list<string>  $badges
     */
    public function forEvent(EventData $event, array $badges = []): Node
    {
        return new Node(
            id: NodeId::fromString($event->id),
            type: NodeType::Event,
            label: ClassName::shortName($event->class),
            subtitle: null,
            metadata: [
                'class' => $event->class,
                'namespace' => ClassName::namespace($event->class),
                'file' => $event->file,
                'listener_classes' => $event->listenerClasses,
                'closure_listener_count' => $event->closureListenerCount,
                'warnings' => $event->warnings,
            ],
            badges: $badges,
            status: $event->status,
        );
    }

    public function forListener(ListenerData $listener): Node
    {
        return new Node(
            id: NodeId::fromString($listener->id),
            type: NodeType::Listener,
            label: ClassName::shortName($listener->class),
            subtitle: $listener->queued ? 'queued' : null,
            metadata: [
                'class' => $listener->class,
                'namespace' => ClassName::namespace($listener->class),
                'file' => $listener->file,
                'events' => $listener->events,
                'queued' => $listener->queued,
                'dispatches' => DispatchReference::listToArray($listener->dispatches),
                'warnings' => $listener->warnings,
            ],
            badges: $this->queueBadges($listener->queued, $listener->status),
            status: $listener->status,
        );
    }

    public function forDispatchable(DispatchableData $dispatchable): Node
    {
        return new Node(
            id: NodeId::fromString($dispatchable->id),
            type: $dispatchable->kind->nodeType(),
            label: ClassName::shortName($dispatchable->class),
            subtitle: $dispatchable->queued ? 'queued' : null,
            metadata: [
                'class' => $dispatchable->class,
                'namespace' => ClassName::namespace($dispatchable->class),
                'file' => $dispatchable->file,
                'kind' => $dispatchable->kind->value,
                'queued' => $dispatchable->queued,
                'dispatches' => DispatchReference::listToArray($dispatchable->dispatches),
                'warnings' => $dispatchable->warnings,
            ],
            badges: $this->queueBadges($dispatchable->queued, $dispatchable->status),
            status: $dispatchable->status,
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

    /**
     * @return list<string>
     */
    private function queueBadges(bool $queued, DiscoveryStatus $status): array
    {
        $badges = $queued ? ['Queued'] : [];

        if ($status->isPartial()) {
            $badges[] = 'Partial';
        }

        return $badges;
    }

    public static function polymorphicTargetId(RelationData $relation): string
    {
        $normalizedSource = str_starts_with($relation->sourceModelId, 'model:')
            ? substr($relation->sourceModelId, strlen('model:'))
            : $relation->sourceModelId;

        return 'polymorphic:' . $normalizedSource . ':' . $relation->method;
    }
}
