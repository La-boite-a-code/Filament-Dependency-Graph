<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use DateTimeImmutable;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\ModelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\PanelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\RelationDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\ResourceDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
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
    ) {}

    public function discover(DiscoveryContext $context): ApplicationSnapshot
    {
        $this->warnings = [];

        $panels = $this->discoverPanels($context);
        $resources = $this->discoverResources($context);
        $models = $this->discoverModels($context);

        $models = $this->addResourceModels($models, $resources, $context);

        [$models, $relations] = $this->discoverRelations($models, $context);

        $relations = $this->markInverseRelations($relations);

        usort($models, static fn (ModelData $a, ModelData $b): int => strcmp($a->id, $b->id));
        usort($relations, static fn (RelationData $a, RelationData $b): int => strcmp($a->id, $b->id));
        usort($resources, static fn (ResourceData $a, ResourceData $b): int => strcmp($a->id, $b->id));
        usort($panels, static fn (PanelData $a, PanelData $b): int => strcmp($a->id, $b->id));

        $warnings = $this->aggregateWarnings($models, $relations, $resources);

        return new ApplicationSnapshot(
            fingerprint: $this->fingerprint($context, $models, $relations, $resources, $panels),
            generatedAt: new DateTimeImmutable,
            models: $models,
            relations: $relations,
            resources: $resources,
            panels: $panels,
            warnings: $warnings,
        );
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
     * @return list<DiscoveryWarning>
     */
    private function aggregateWarnings(array $models, array $relations, array $resources): array
    {
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

        usort($warnings, static function (DiscoveryWarning $a, DiscoveryWarning $b): int {
            return [$a->type, $a->class ?? '', $a->method ?? '', $a->message]
                <=> [$b->type, $b->class ?? '', $b->method ?? '', $b->message];
        });

        return $warnings;
    }

    /**
     * @param  list<ModelData>  $models
     * @param  list<RelationData>  $relations
     * @param  list<ResourceData>  $resources
     * @param  list<PanelData>  $panels
     */
    private function fingerprint(
        DiscoveryContext $context,
        array $models,
        array $relations,
        array $resources,
        array $panels,
    ): string {
        $payload = json_encode([
            'context' => $context->toArray(),
            'models' => array_map(static fn (ModelData $model): array => $model->toArray(), $models),
            'relations' => array_map(static fn (RelationData $relation): array => $relation->toArray(), $relations),
            'resources' => array_map(static fn (ResourceData $resource): array => $resource->toArray(), $resources),
            'panels' => array_map(static fn (PanelData $panel): array => $panel->toArray(), $panels),
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
