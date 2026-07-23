<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Application\BuildDependencyGraph;
use LaBoiteACode\DependencyGraph\Application\DiscoverApplication;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;

function buildFixtureGraph(?GraphQuery $query = null): Graph
{
    $snapshot = app(DiscoverApplication::class)->execute(test()->fixtureContext());

    return app(BuildDependencyGraph::class)->execute($snapshot, $query);
}

const FDG_MODEL_PREFIX = 'model:la-boite-a-code.dependency-graph.tests.fixtures.models.';

it('builds the full graph without a query', function (): void {
    $graph = buildFixtureGraph();

    expect($graph->hasNode('panel:admin'))->toBeTrue()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'audit-entry'))->toBeTrue();
});

it('keeps every discovered model in the laravel scope', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(scope: GraphScope::Laravel));

    expect($graph->hasNode(FDG_MODEL_PREFIX . 'audit-entry'))->toBeTrue()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'order'))->toBeTrue();
});

it('restricts the filament scope to resource models and their neighbourhood', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(scope: GraphScope::Filament));

    expect($graph->hasNode(FDG_MODEL_PREFIX . 'order'))->toBeTrue()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'order-item'))->toBeTrue()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'audit-entry'))->toBeFalse()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'report'))->toBeFalse();
});

it('filters panels and the resources they register', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(
        scope: GraphScope::Filament,
        panelIds: ['operations'],
    ));

    expect($graph->hasNode('panel:operations'))->toBeTrue()
        ->and($graph->hasNode('panel:admin'))->toBeFalse()
        ->and($graph->hasNode('resource:la-boite-a-code.dependency-graph.tests.fixtures.resources.user-resource'))->toBeTrue()
        ->and($graph->hasNode('resource:la-boite-a-code.dependency-graph.tests.fixtures.resources.customer-resource'))->toBeFalse();
});

it('filters node types', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(
        scope: GraphScope::Laravel,
        nodeTypes: [NodeType::Model],
    ));

    expect($graph->nodesOfType(NodeType::Panel))->toBe([])
        ->and($graph->nodesOfType(NodeType::Resource))->toBe([])
        ->and($graph->nodesOfType(NodeType::Model))->not->toBe([]);
});

it('filters relation types', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(
        scope: GraphScope::Laravel,
        relationTypes: [RelationType::BelongsTo],
    ));

    $types = [];

    foreach ($graph->edgesOfType(EdgeType::ModelRelation) as $edge) {
        $types[$edge->metadata['relation_type']] = true;
    }

    expect(array_keys($types))->toBe([RelationType::BelongsTo->value]);
});

it('excludes orphans on demand', function (): void {
    $with = buildFixtureGraph(new GraphQuery(scope: GraphScope::Laravel, includeOrphans: true));
    $without = buildFixtureGraph(new GraphQuery(scope: GraphScope::Laravel, includeOrphans: false));

    expect($with->hasNode(FDG_MODEL_PREFIX . 'audit-entry'))->toBeTrue()
        ->and($without->hasNode(FDG_MODEL_PREFIX . 'audit-entry'))->toBeFalse();
});

it('focuses the graph on a node with depth and direction', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(
        scope: GraphScope::Laravel,
        focusNodeId: FDG_MODEL_PREFIX . 'order',
        depth: 1,
    ));

    expect($graph->hasNode(FDG_MODEL_PREFIX . 'order'))->toBeTrue()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'customer'))->toBeTrue()
        ->and($graph->hasNode(FDG_MODEL_PREFIX . 'user'))->toBeFalse();
});

it('detects the fixture circular dependency between user and team', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(scope: GraphScope::Laravel));

    expect($graph->node(FDG_MODEL_PREFIX . 'user')?->badges)->toContain('Cycle')
        ->and($graph->node(FDG_MODEL_PREFIX . 'team')?->badges)->toContain('Cycle');
});

it('marks models without resources', function (): void {
    $graph = buildFixtureGraph(new GraphQuery(scope: GraphScope::Laravel));

    expect($graph->node(FDG_MODEL_PREFIX . 'order-item')?->badges)->toContain('No Resource')
        ->and($graph->node(FDG_MODEL_PREFIX . 'order')?->badges)->toContain('Resource');
});
