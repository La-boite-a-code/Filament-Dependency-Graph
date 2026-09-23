<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Contracts\DependencyGraphManager;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Facades\DependencyGraph;

it('discovers through the facade', function (): void {
    expect(DependencyGraph::discover())->toBeInstanceOf(ApplicationSnapshot::class);
});

it('builds graphs through the facade', function (): void {
    $graph = DependencyGraph::graph(new GraphQuery(scope: GraphScope::Laravel));

    expect($graph)->toBeInstanceOf(Graph::class)
        ->and($graph->nodeCount())->toBeGreaterThan(0);
});

it('exports through the facade', function (): void {
    $json = DependencyGraph::export('json');
    $mermaid = DependencyGraph::export('mermaid');

    expect(json_decode($json, true))->toHaveKey('schemaVersion')
        ->and($mermaid)->toContain('flowchart');
});

it('clears the cache through the facade', function (): void {
    DependencyGraph::clearCache();

    expect(true)->toBeTrue();
});

it('reads the snapshot once per request and forgets it when the cache is cleared', function (): void {
    $manager = app(DependencyGraphManager::class);
    $first = $manager->discover();

    expect($manager->discover())->toBe($first);

    $manager->clearCache();

    expect($manager->discover())->not->toBe($first);
});

it('reads the snapshot matching the current configuration', function (): void {
    $manager = app(DependencyGraphManager::class);
    $withViews = $manager->discover();

    config()->set('filament-dependency-graph.views.enabled', false);

    expect($withViews->views->isEmpty())->toBeFalse()
        ->and($manager->discover()->views->isEmpty())->toBeTrue();
});

it('scopes the manager to the request and never caches it in the facade', function (): void {
    $manager = app(DependencyGraphManager::class);

    expect(DependencyGraph::getFacadeRoot())->toBe($manager);

    app()->forgetScopedInstances();

    expect(app(DependencyGraphManager::class))->not->toBe($manager)
        ->and(DependencyGraph::getFacadeRoot())->toBe(app(DependencyGraphManager::class));
});
