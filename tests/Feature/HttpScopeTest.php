<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Application\BuildDependencyGraph;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\GraphBuilder;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Actions\PlaceOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderArchived;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ShipOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

function httpGraph(GraphScope $scope = GraphScope::Http, ?string $middleware = null): Graph
{
    $snapshot = app(ApplicationDiscovery::class)->discover(test()->fixtureContext());

    return app(BuildDependencyGraph::class)->execute($snapshot, new GraphQuery(scope: $scope, middleware: $middleware));
}

/**
 * @return array<string, int>
 */
function countNodeTypes(Graph $graph): array
{
    $counts = [];

    foreach ($graph->nodes as $node) {
        $counts[$node->type->value] = ($counts[$node->type->value] ?? 0) + 1;
    }

    ksort($counts);

    return $counts;
}

/**
 * @return list<string>
 */
function labelsOfType(Graph $graph, NodeType $type): array
{
    $labels = array_map(static fn (Node $node): string => $node->label, $graph->nodesOfType($type));
    sort($labels);

    return $labels;
}

it('shows routes and everything they lead to in the HTTP scope', function (): void {
    $graph = httpGraph();

    expect(countNodeTypes($graph))->toBe([
        'controller' => 2,
        'event' => 2,
        'form_request' => 2,
        'job' => 3,
        'listener' => 2,
        'livewire_component' => 1,
        'mailable' => 1,
        'model' => 4,
        'notification' => 1,
        'policy' => 2,
        'route' => 11,
        'view' => 6,
    ])
        ->and(labelsOfType($graph, NodeType::Model))->toBe(['Customer', 'Order', 'Product', 'User'])
        ->and(labelsOfType($graph, NodeType::View))->toBe([
            'layouts.app',
            'livewire.order-dashboard',
            'mail.order-shipped',
            'mail.order-updated',
            'orders.index',
            'pages.about',
        ]);
});

it('links routes, controllers, requests, models, policies, dispatches and listeners', function (): void {
    $graph = httpGraph();

    expect(count($graph->edgesOfType(EdgeType::RouteHandledByController)))->toBe(6)
        ->and(count($graph->edgesOfType(EdgeType::RouteRendersLivewire)))->toBe(1)
        ->and(count($graph->edgesOfType(EdgeType::ControllerValidatesWith)))->toBe(2)
        ->and(count($graph->edgesOfType(EdgeType::ControllerUsesModel)))->toBe(3)
        ->and(count($graph->edgesOfType(EdgeType::ModelGuardedByPolicy)))->toBe(2)
        ->and(count($graph->edgesOfType(EdgeType::EventHandledByListener)))->toBe(3)
        ->and(count($graph->edgesOfType(EdgeType::Dispatches)))->toBe(7)
        ->and(count($graph->edgesOfType(EdgeType::RendersView)))->toBe(6);
});

it('shows rendered views as leaves of the HTTP scope, per action', function (): void {
    $graph = httpGraph();

    expect($graph->nodesOfType(NodeType::ExternalView))->toBe([])
        ->and($graph->nodesOfType(NodeType::BladeComponent))->toBe([])
        // orders.index extends layouts.app, both rendered: no edge between them.
        ->and(array_filter($graph->edges, static fn ($edge): bool => $edge->type->isViewReference()))->toBe([])
        ->and(labelsOfType(httpGraph(middleware: 'auth'), NodeType::View))->toBe(['mail.order-shipped', 'mail.order-updated']);
});

it('merges the actions of one controller into a single edge per target', function (): void {
    $graph = httpGraph();
    $controllerId = StableIdentifier::controller(OrderController::class);

    $usesOrder = $graph->edge(StableIdentifier::edge(EdgeType::ControllerUsesModel, $controllerId, StableIdentifier::model(Order::class)));
    $shipsOrder = $graph->edge(StableIdentifier::edge(EdgeType::Dispatches, $controllerId, 'job:' . StableIdentifier::normalizeClass(ShipOrder::class)));

    expect($usesOrder)->not->toBeNull()
        ->and($usesOrder->label)->toBe('destroy, index, show, store, update')
        ->and($usesOrder->metadata['sources'])->toBe(['binding', 'static'])
        ->and($shipsOrder)->not->toBeNull()
        ->and($shipsOrder->label)->toBe('job')
        ->and($shipsOrder->metadata['methods'])->toBe(['store'])
        ->and($shipsOrder->metadata['via'])->toBe([PlaceOrder::class])
        ->and($shipsOrder->metadata['confidence'])->toBe('static');
});

it('flags events that nothing in the application dispatches', function (): void {
    $graph = httpGraph();

    expect($graph->node(StableIdentifier::event(OrderArchived::class))->badges)->toContain('No dispatcher found')
        ->and($graph->node(StableIdentifier::event(OrderPlaced::class))->badges)->not->toContain('No dispatcher found');
});

it('filters routes by middleware and keeps only what they reach', function (): void {
    $withAuth = httpGraph(middleware: 'auth');
    $withoutAuth = httpGraph(middleware: '!auth');

    expect(labelsOfType($withAuth, NodeType::Route))->toBe([
        'DELETE /orders/{order}',
        'POST /orders',
        'PUT /orders/{order}',
    ])
        ->and(labelsOfType($withAuth, NodeType::Event))->toBe(['OrderPlaced'])
        ->and(count($withoutAuth->nodesOfType(NodeType::Route)))->toBe(8)
        ->and($withoutAuth->nodesOfType(NodeType::Event))->toBe([])
        ->and($withoutAuth->nodesOfType(NodeType::Listener))->toBe([]);
});

it('keeps the HTTP map out of the Filament and Laravel scopes', function (GraphScope $scope): void {
    $graph = httpGraph($scope);

    foreach (NodeType::httpTypes() as $type) {
        expect($graph->nodesOfType($type))->toBe([]);
    }
})->with([GraphScope::Filament, GraphScope::Laravel]);

it('builds the Laravel scope exactly as the full graph without its HTTP and view nodes', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $full = app(GraphBuilder::class)->build($snapshot);
    $kept = array_map(
        static fn (Node $node): string => $node->id->value,
        array_filter($full->nodes, static fn (Node $node): bool => ! $node->type->isHttp() && ! $node->type->isView()),
    );

    $laravel = app(BuildDependencyGraph::class)->execute($snapshot, new GraphQuery(scope: GraphScope::Laravel, includeOrphans: true));

    expect($snapshot->http->isEmpty())->toBeFalse()
        ->and($snapshot->views->isEmpty())->toBeFalse()
        ->and($laravel->toArray())->toBe($full->subgraph(array_values($kept))->toArray());
});

it('builds the Filament scope exactly as it does from the full graph', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $builder = app(BuildDependencyGraph::class);
    $full = app(GraphBuilder::class)->build($snapshot);

    $expected = (fn (Graph $graph): Graph => $this->restrictToFilamentScope($graph))->call($builder, $full);
    $filament = $builder->execute($snapshot, new GraphQuery(scope: GraphScope::Filament, includeOrphans: true));

    expect($filament->toArray())->toBe($expected->toArray());
});
