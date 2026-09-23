<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Application\BuildDependencyGraph;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\FilamentViews\Widgets\StatsWidget;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\OrderDashboard;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Alert;

function viewsGraph(mixed ...$overrides): Graph
{
    $snapshot = app(ApplicationDiscovery::class)->discover(test()->fixtureContext(...$overrides));

    return app(BuildDependencyGraph::class)->execute($snapshot, new GraphQuery(scope: GraphScope::Views));
}

/**
 * @return list<string>
 */
function targetsOf(Graph $graph, string $sourceId, EdgeType $type): array
{
    $targets = [];

    foreach ($graph->outgoingEdges($sourceId) as $edge) {
        if ($edge->type === $type) {
            $targets[] = $edge->target->value;
        }
    }

    sort($targets);

    return $targets;
}

it('shows every template, what renders it and what it renders', function (): void {
    $graph = viewsGraph();

    expect(count($graph->nodesOfType(NodeType::View)))->toBe(15)
        ->and(count($graph->nodesOfType(NodeType::FilamentComponent)))->toBe(2)
        ->and(count($graph->nodesOfType(NodeType::DynamicView)))->toBe(2)
        ->and($graph->nodesOfType(NodeType::Model))->toBe([])
        ->and($graph->nodesOfType(NodeType::Resource))->toBe([])
        ->and(targetsOf($graph, 'view:orders.index', EdgeType::ViewIncludes))->toBe([
            'external-view:orders.partials.missing',
            'view:orders.partials.empty',
            'view:orders.partials.row',
        ])
        ->and(targetsOf($graph, 'view:orders.index', EdgeType::ViewExtends))->toBe(['view:layouts.app'])
        ->and(targetsOf($graph, 'view:layouts.app', EdgeType::ViewRendersLivewire))->toBe([
            'livewire:la-boite-a-code.dependency-graph.tests.fixtures.livewire.standalone-counter',
        ])
        ->and(targetsOf($graph, StableIdentifier::controller(OrderController::class), EdgeType::RendersView))->toBe(['view:orders.index'])
        ->and(targetsOf($graph, StableIdentifier::livewireComponent(OrderDashboard::class), EdgeType::RendersView))->toBe([
            'view:layouts.app',
            'view:livewire.order-dashboard',
        ])
        ->and(targetsOf($graph, StableIdentifier::filamentComponent(StatsWidget::class), EdgeType::RendersView))->toBe(['view:filament.widgets.stats']);
});

it('merges repeated references into one edge listing every line', function (): void {
    $edge = viewsGraph()->edge(StableIdentifier::edge(EdgeType::ViewIncludes, 'view:orders.index', 'view:orders.partials.row'));

    expect($edge->label)->toBe('@include, @each')
        ->and($edge->metadata['lines'])->toBe([9, 12]);
});

it('flags views without any detected reference', function (): void {
    $graph = viewsGraph();

    expect($graph->node('view:unused')->badges)->toContain('No reference found')
        ->and($graph->node('view:orders.partials.row')->badges)->not->toContain('No reference found')
        ->and($graph->node('external-view:orders.partials.missing')->badges)->toContain('Missing');
});

it('keeps Blade class components reached from templates', function (): void {
    $graph = viewsGraph();
    $alert = StableIdentifier::bladeComponent(Alert::class);

    expect($graph->node($alert)?->subtitle)->toBe('<x-alert>')
        ->and(targetsOf($graph, $alert, EdgeType::RendersView))->toBe(['view:components.alert']);
});

it('leaves the Filament and Laravel scopes untouched by the view map', function (GraphScope $scope): void {
    $build = static fn (bool $views): array => app(BuildDependencyGraph::class)->execute(
        app(ApplicationDiscovery::class)->discover(test()->fixtureContext(discoverViews: $views)),
        new GraphQuery(scope: $scope),
    )->toArray();

    expect($build(true))->toBe($build(false));
})->with([GraphScope::Filament, GraphScope::Laravel]);
