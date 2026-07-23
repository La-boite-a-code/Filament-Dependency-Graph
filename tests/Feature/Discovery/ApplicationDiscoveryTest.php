<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;

it('discovers a complete application snapshot from the fixture domain', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());

    expect($snapshot)->toBeInstanceOf(ApplicationSnapshot::class)
        ->and(count($snapshot->models))->toBeGreaterThanOrEqual(13)
        ->and(count($snapshot->relations))->toBeGreaterThanOrEqual(20)
        ->and(count($snapshot->resources))->toBe(5)
        ->and(count($snapshot->panels))->toBe(3);
});

it('produces deterministic output across two discovery runs', function (): void {
    $discovery = app(ApplicationDiscovery::class);

    $first = $discovery->discover($this->fixtureContext());
    $second = $discovery->discover($this->fixtureContext());

    expect($first->fingerprint)->toBe($second->fingerprint)
        ->and(array_map(fn (ModelData $model): string => $model->id, $first->models))
        ->toBe(array_map(fn (ModelData $model): string => $model->id, $second->models));
});

it('sorts snapshot collections deterministically', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());

    $modelIds = array_map(fn (ModelData $model): string => $model->id, $snapshot->models);
    $relationIds = array_map(fn (RelationData $relation): string => $relation->id, $snapshot->relations);
    $resourceIds = array_map(fn (ResourceData $resource): string => $resource->id, $snapshot->resources);
    $panelIds = array_map(fn (PanelData $panel): string => $panel->id, $snapshot->panels);

    $sorted = static function (array $values): array {
        $copy = $values;
        sort($copy, SORT_STRING);

        return $copy;
    };

    expect($modelIds)->toBe($sorted($modelIds))
        ->and($relationIds)->toBe($sorted($relationIds))
        ->and($resourceIds)->toBe($sorted($resourceIds))
        ->and($panelIds)->toBe($sorted($panelIds));
});

it('aggregates discovery warnings from partial models and relations', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());

    $types = array_map(
        static fn ($warning): string => $warning->type,
        $snapshot->warnings,
    );

    expect($types)->toContain('model_discovery')
        ->and($types)->toContain('relation_discovery');
});
