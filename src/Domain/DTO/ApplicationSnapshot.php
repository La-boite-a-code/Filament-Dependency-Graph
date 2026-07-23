<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

use DateTimeImmutable;
use DateTimeInterface;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;

final readonly class ApplicationSnapshot
{
    /**
     * @param  list<ModelData>  $models
     * @param  list<RelationData>  $relations
     * @param  list<ResourceData>  $resources
     * @param  list<PanelData>  $panels
     * @param  list<DiscoveryWarning>  $warnings
     */
    public function __construct(
        public string $fingerprint,
        public DateTimeImmutable $generatedAt,
        public array $models,
        public array $relations,
        public array $resources,
        public array $panels,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{fingerprint: string, generated_at: string, models: list<array<string, mixed>>, relations: list<array<string, mixed>>, resources: list<array<string, mixed>>, panels: list<array{id: string, path: string|null, domain: string|null, resource_ids: list<string>}>, warnings: list<array{type: string, message: string, class?: string|null, method?: string|null, exception_class?: string|null}>} $data */
        return new self(
            fingerprint: $data['fingerprint'],
            generatedAt: new DateTimeImmutable($data['generated_at']),
            models: array_map(
                static fn (array $model): ModelData => ModelData::fromArray($model),
                $data['models'],
            ),
            relations: array_map(
                static fn (array $relation): RelationData => RelationData::fromArray($relation),
                $data['relations'],
            ),
            resources: array_map(
                static fn (array $resource): ResourceData => ResourceData::fromArray($resource),
                $data['resources'],
            ),
            panels: array_map(
                static fn (array $panel): PanelData => PanelData::fromArray($panel),
                $data['panels'],
            ),
            warnings: array_map(
                static fn (array $warning): DiscoveryWarning => DiscoveryWarning::fromArray($warning),
                $data['warnings'],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'generated_at' => $this->generatedAt->format(DateTimeInterface::ATOM),
            'models' => array_map(
                static fn (ModelData $model): array => $model->toArray(),
                $this->models,
            ),
            'relations' => array_map(
                static fn (RelationData $relation): array => $relation->toArray(),
                $this->relations,
            ),
            'resources' => array_map(
                static fn (ResourceData $resource): array => $resource->toArray(),
                $this->resources,
            ),
            'panels' => array_map(
                static fn (PanelData $panel): array => $panel->toArray(),
                $this->panels,
            ),
            'warnings' => array_map(
                static fn (DiscoveryWarning $warning): array => $warning->toArray(),
                $this->warnings,
            ),
        ];
    }

    public function model(string $modelId): ?ModelData
    {
        foreach ($this->models as $model) {
            if ($model->id === $modelId) {
                return $model;
            }
        }

        return null;
    }

    public function modelByClass(string $class): ?ModelData
    {
        foreach ($this->models as $model) {
            if ($model->class === $class) {
                return $model;
            }
        }

        return null;
    }

    public function resource(string $resourceId): ?ResourceData
    {
        foreach ($this->resources as $resource) {
            if ($resource->id === $resourceId) {
                return $resource;
            }
        }

        return null;
    }

    public function panel(string $panelId): ?PanelData
    {
        foreach ($this->panels as $panel) {
            if ($panel->id === $panelId) {
                return $panel;
            }
        }

        return null;
    }
}
