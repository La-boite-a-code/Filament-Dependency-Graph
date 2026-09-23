<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use DateTimeImmutable;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\HttpMapDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\LivewireComponentDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\ModelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\PanelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\PolicyDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\RelationDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\ResourceDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Discovery\Support\SourceScanner;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\PolicyData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use Throwable;

/**
 * Coordinates panel, resource, model and relation discovery into one
 * immutable application snapshot with deterministic ordering.
 */
final class LaravelApplicationDiscoverer implements ApplicationDiscovery
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly ModelDiscoverer $modelDiscoverer,
        private readonly RelationDiscoverer $relationDiscoverer,
        private readonly PanelDiscoverer $panelDiscoverer,
        private readonly ResourceDiscoverer $resourceDiscoverer,
        private readonly LivewireComponentDiscoverer $livewireComponentDiscoverer,
        private readonly HttpMapDiscoverer $httpMapDiscoverer,
        private readonly PolicyDiscoverer $policyDiscoverer,
        private readonly SourceScanner $scanner,
    ) {}

    public function discover(DiscoveryContext $context): ApplicationSnapshot
    {
        $this->warnings = [];

        try {
            return $this->run($context);
        } finally {
            // Tokenized sources are only reused within one discovery run.
            $this->scanner->forget();
        }
    }

    private function run(DiscoveryContext $context): ApplicationSnapshot
    {
        $panels = $this->discoverPanels($context);
        $resources = $this->discoverResources($context);
        $livewireComponents = $this->discoverLivewireComponents($context);
        $http = $this->discoverHttpMap($context);
        $models = $this->discoverModels($context);

        $models = $this->addResourceModels($models, $resources, $context);
        $models = $this->addLivewireComponentModels($models, $livewireComponents, $context);
        $models = $this->addModelClasses($models, $http->referencedModelClasses(), $context);

        [$models, $relations] = $this->discoverRelations($models, $context);

        $http = $http->withPolicies($this->discoverPolicies($models, $context));

        $relations = $this->markInverseRelations($relations);

        usort($models, static fn (ModelData $a, ModelData $b): int => strcmp($a->id, $b->id));
        usort($relations, static fn (RelationData $a, RelationData $b): int => strcmp($a->id, $b->id));
        usort($resources, static fn (ResourceData $a, ResourceData $b): int => strcmp($a->id, $b->id));
        usort($panels, static fn (PanelData $a, PanelData $b): int => strcmp($a->id, $b->id));
        usort(
            $livewireComponents,
            static fn (LivewireComponentData $a, LivewireComponentData $b): int => strcmp($a->id, $b->id),
        );

        $warnings = $this->aggregateWarnings($models, $relations, $resources, $livewireComponents, $http);

        return new ApplicationSnapshot(
            fingerprint: $this->fingerprint(
                $context,
                $models,
                $relations,
                $resources,
                $panels,
                $livewireComponents,
                $http,
            ),
            generatedAt: new DateTimeImmutable,
            models: $models,
            relations: $relations,
            resources: $resources,
            panels: $panels,
            warnings: $warnings,
            livewireComponents: $livewireComponents,
            http: $http,
        );
    }

    private function discoverHttpMap(DiscoveryContext $context): HttpMapData
    {
        try {
            $http = $this->httpMapDiscoverer->discover($context);
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'http_discovery_failed',
                message: sprintf('HTTP map discovery failed: %s', $exception->getMessage()),
                exceptionClass: $exception::class,
            );

            $http = new HttpMapData;
        }

        $this->drainWarnings($this->httpMapDiscoverer);

        return $http;
    }

    /**
     * @param  list<ModelData>  $models
     * @return list<PolicyData>
     */
    private function discoverPolicies(array $models, DiscoveryContext $context): array
    {
        if (! $context->discoverHttp) {
            return [];
        }

        try {
            return $this->policyDiscoverer->discover(
                array_map(static fn (ModelData $model): string => $model->class, $models),
                $context,
            );
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'policy_discovery_failed',
                message: sprintf('Policy discovery failed: %s', $exception->getMessage()),
                exceptionClass: $exception::class,
            );

            return [];
        } finally {
            $this->drainWarnings($this->policyDiscoverer);
        }
    }

    /**
     * Models referenced by routes and controllers take part in the graph
     * even when they live outside the configured model paths.
     *
     * @param  array<string, ModelData>  $models
     * @param  list<string>  $classes
     * @return array<string, ModelData>
     */
    private function addModelClasses(array $models, array $classes, DiscoveryContext $context): array
    {
        foreach ($classes as $class) {
            if (isset($models[StableIdentifier::model($class)])) {
                continue;
            }

            $model = $this->discoverSingleClass($class, $context);

            if ($model !== null) {
                $models[$model->id] = $model;
            }
        }

        return $models;
    }

    /**
     * @return list<PanelData>
     */
    private function discoverPanels(DiscoveryContext $context): array
    {
        try {
            $panels = $this->panelDiscoverer->discover($context);
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'panel_discovery_failed',
                message: sprintf('Filament panel discovery failed: %s', $exception->getMessage()),
                exceptionClass: $exception::class,
            );

            $panels = [];
        }

        $this->drainWarnings($this->panelDiscoverer);

        return $panels;
    }

    /**
     * @return list<ResourceData>
     */
    private function discoverResources(DiscoveryContext $context): array
    {
        try {
            $resources = $this->resourceDiscoverer->discover($context);
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'resource_discovery_failed',
                message: sprintf('Filament resource discovery failed: %s', $exception->getMessage()),
                exceptionClass: $exception::class,
            );

            $resources = [];
        }

        $this->drainWarnings($this->resourceDiscoverer);

        return $resources;
    }

    /**
     * @return list<LivewireComponentData>
     */
    private function discoverLivewireComponents(DiscoveryContext $context): array
    {
        try {
            $components = $this->livewireComponentDiscoverer->discover($context);
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'livewire_component_discovery_failed',
                message: sprintf('Livewire component discovery failed: %s', $exception->getMessage()),
                exceptionClass: $exception::class,
            );

            $components = [];
        }

        $this->drainWarnings($this->livewireComponentDiscoverer);

        return $components;
    }

    /**
     * @return array<string, ModelData> Keyed by stable model id.
     */
    private function discoverModels(DiscoveryContext $context): array
    {
        $models = [];

        foreach ($this->modelDiscoverer->discover($context) as $model) {
            $models[$model->id] = $model;
        }

        $this->drainWarnings($this->modelDiscoverer);

        return $models;
    }

    /**
     * Models exposed through a Filament resource take part in the graph even
     * when they live outside the configured model paths.
     *
     * @param  array<string, ModelData>  $models
     * @param  list<ResourceData>  $resources
     * @return array<string, ModelData>
     */
    private function addResourceModels(array $models, array $resources, DiscoveryContext $context): array
    {
        foreach ($resources as $resource) {
            if ($resource->modelId === null || isset($models[$resource->modelId])) {
                continue;
            }

            $model = $this->discoverSingleClass($resource->modelClass, $context);

            if ($model !== null) {
                $models[$model->id] = $model;
            }
        }

        return $models;
    }

    /**
     * Models referenced by Livewire component properties, actions or source
     * code take part in the Laravel graph even when they live outside the
     * configured model paths.
     *
     * @param  array<string, ModelData>  $models
     * @param  list<LivewireComponentData>  $components
     * @return array<string, ModelData>
     */
    private function addLivewireComponentModels(
        array $models,
        array $components,
        DiscoveryContext $context,
    ): array {
        foreach ($components as $component) {
            foreach (array_keys($component->modelReferences) as $modelClass) {
                $modelId = StableIdentifier::model($modelClass);

                if (isset($models[$modelId])) {
                    continue;
                }

                $model = $this->discoverSingleClass($modelClass, $context);

                if ($model !== null) {
                    $models[$model->id] = $model;
                }
            }
        }

        return $models;
    }

    /**
     * Discovers relations for every model, then follows relation targets to
     * models that were not part of the initial scan until the model set is
     * stable.
     *
     * @param  array<string, ModelData>  $models
     * @return array{0: list<ModelData>, 1: list<RelationData>}
     */
    private function discoverRelations(array $models, DiscoveryContext $context): array
    {
        $relations = [];
        $queue = array_values($models);

        while ($queue !== []) {
            $model = array_shift($queue);

            foreach ($this->relationDiscoverer->discover($model, $context) as $relation) {
                if (isset($relations[$relation->id])) {
                    continue;
                }

                $relations[$relation->id] = $relation;

                $targetClass = $relation->relatedClass;

                if ($targetClass === null || $relation->targetModelId === null) {
                    continue;
                }

                if (isset($models[$relation->targetModelId])) {
                    continue;
                }

                $target = $this->discoverSingleClass($targetClass, $context);

                if ($target !== null) {
                    $models[$target->id] = $target;
                    $queue[] = $target;
                }
            }

            $this->drainWarnings($this->relationDiscoverer);
        }

        return [array_values($models), array_values($relations)];
    }

    private function discoverSingleClass(string $class, DiscoveryContext $context): ?ModelData
    {
        if (! $this->modelDiscoverer instanceof EloquentModelDiscoverer) {
            return null;
        }

        $model = $this->modelDiscoverer->discoverClass($class, $context);

        $this->drainWarnings($this->modelDiscoverer);

        return $model;
    }

    /**
     * Marks relations whose inverse could be found on the target model.
     * The match is heuristic: relation types must be compatible and keys must
     * agree when both sides expose them.
     *
     * @param  list<RelationData>  $relations
     * @return list<RelationData>
     */
    private function markInverseRelations(array $relations): array
    {
        $byPair = [];

        foreach ($relations as $index => $relation) {
            if ($relation->targetModelId === null) {
                continue;
            }

            $byPair[$relation->sourceModelId . '|' . $relation->targetModelId][] = $index;
        }

        foreach ($relations as $index => $relation) {
            if ($relation->targetModelId === null) {
                continue;
            }

            $candidates = $byPair[$relation->targetModelId . '|' . $relation->sourceModelId] ?? [];

            foreach ($candidates as $candidateIndex) {
                $candidate = $relations[$candidateIndex];

                if ($this->isInversePair($relation, $candidate)) {
                    $relations[$index] = $relation->withInverseDiscovered(true);

                    break;
                }
            }
        }

        return $relations;
    }

    private function isInversePair(RelationData $relation, RelationData $candidate): bool
    {
        $compatible = match ($relation->type) {
            RelationType::BelongsTo => in_array($candidate->type, [RelationType::HasOne, RelationType::HasMany], true),
            RelationType::HasOne,
            RelationType::HasMany => $candidate->type === RelationType::BelongsTo,
            RelationType::BelongsToMany => $candidate->type === RelationType::BelongsToMany,
            RelationType::MorphOne,
            RelationType::MorphMany => $candidate->type === RelationType::MorphTo,
            RelationType::MorphToMany => $candidate->type === RelationType::MorphedByMany,
            RelationType::MorphedByMany => $candidate->type === RelationType::MorphToMany,
            default => false,
        };

        if (! $compatible) {
            return false;
        }

        if (
            $relation->type === RelationType::BelongsToMany
            && $relation->pivotTable !== null
            && $candidate->pivotTable !== null
        ) {
            return $relation->pivotTable === $candidate->pivotTable;
        }

        if ($relation->foreignKey !== null && $candidate->foreignKey !== null) {
            return $relation->foreignKey === $candidate->foreignKey;
        }

        return true;
    }

    /**
     * @param  list<ModelData>  $models
     * @param  list<RelationData>  $relations
     * @param  list<ResourceData>  $resources
     * @param  list<LivewireComponentData>  $livewireComponents
     * @return list<DiscoveryWarning>
     */
    private function aggregateWarnings(
        array $models,
        array $relations,
        array $resources,
        array $livewireComponents,
        HttpMapData $http,
    ): array {
        $warnings = $this->warnings;

        foreach ($models as $model) {
            foreach ($model->warnings as $message) {
                $warnings[] = new DiscoveryWarning(
                    type: 'model_discovery',
                    message: $message,
                    class: $model->class,
                );
            }
        }

        foreach ($relations as $relation) {
            foreach ($relation->warnings as $message) {
                $warnings[] = new DiscoveryWarning(
                    type: 'relation_discovery',
                    message: $message,
                    class: $relation->sourceModelId,
                    method: $relation->method,
                );
            }
        }

        foreach ($resources as $resource) {
            foreach ($resource->warnings as $message) {
                $warnings[] = new DiscoveryWarning(
                    type: 'resource_discovery',
                    message: $message,
                    class: $resource->class,
                );
            }
        }

        foreach ($livewireComponents as $component) {
            foreach ($component->warnings as $message) {
                $warnings[] = new DiscoveryWarning(
                    type: 'livewire_component_discovery',
                    message: $message,
                    class: $component->class,
                );
            }
        }

        foreach ($this->httpWarnings($http) as [$type, $class, $message]) {
            $warnings[] = new DiscoveryWarning(type: $type, message: $message, class: $class);
        }

        usort($warnings, static function (DiscoveryWarning $a, DiscoveryWarning $b): int {
            return [$a->type, $a->class ?? '', $a->method ?? '', $a->message]
                <=> [$b->type, $b->class ?? '', $b->method ?? '', $b->message];
        });

        return $warnings;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> Type, class and message.
     */
    private function httpWarnings(HttpMapData $http): array
    {
        $warnings = [];

        $groups = [
            'route_discovery' => $http->routes,
            'controller_discovery' => $http->controllers,
            'form_request_discovery' => $http->formRequests,
            'policy_discovery' => $http->policies,
            'event_discovery' => $http->events,
            'listener_discovery' => $http->listeners,
            'dispatchable_discovery' => $http->dispatchables,
        ];

        foreach ($groups as $type => $items) {
            foreach ($items as $item) {
                $subject = $item instanceof RouteData ? $item->label() : $item->class;

                foreach ($item->warnings as $message) {
                    $warnings[] = [$type, $subject, $message];
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  list<ModelData>  $models
     * @param  list<RelationData>  $relations
     * @param  list<ResourceData>  $resources
     * @param  list<PanelData>  $panels
     * @param  list<LivewireComponentData>  $livewireComponents
     */
    private function fingerprint(
        DiscoveryContext $context,
        array $models,
        array $relations,
        array $resources,
        array $panels,
        array $livewireComponents,
        HttpMapData $http,
    ): string {
        $payload = json_encode([
            'context' => $context->toArray(),
            'models' => array_map(static fn (ModelData $model): array => $model->toArray(), $models),
            'relations' => array_map(static fn (RelationData $relation): array => $relation->toArray(), $relations),
            'resources' => array_map(static fn (ResourceData $resource): array => $resource->toArray(), $resources),
            'panels' => array_map(static fn (PanelData $panel): array => $panel->toArray(), $panels),
            'livewire_components' => array_map(
                static fn (LivewireComponentData $component): array => $component->toArray(),
                $livewireComponents,
            ),
            'http' => $http->toArray(),
        ]);

        return sha1($payload === false ? '' : $payload);
    }

    private function drainWarnings(object $discoverer): void
    {
        if ($discoverer instanceof CollectsDiscoveryWarnings) {
            $this->warnings = [...$this->warnings, ...$discoverer->pullWarnings()];
        }
    }
}
