<?php

declare(strict_types=1);

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
