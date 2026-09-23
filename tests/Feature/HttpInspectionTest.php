<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Application\BuildDependencyGraph;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Inspection\DefaultNodeInspector;
use LaBoiteACode\DependencyGraph\Inspection\EdgeInspector;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ShipOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests\StoreOrderRequest;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

function inspectableHttpGraph(mixed ...$overrides): Graph
{
    $snapshot = app(ApplicationDiscovery::class)->discover(test()->fixtureContext(...$overrides));

    return app(BuildDependencyGraph::class)->execute($snapshot, new GraphQuery(scope: GraphScope::Http));
}

/**
 * @return array<string, array<string, mixed>>
 */
function inspectNode(Graph $graph, string $nodeId): array
{
    $inspection = app(DefaultNodeInspector::class)->inspect($graph->node($nodeId), $graph)->toArray();
    $sections = [];

    foreach ($inspection['sections'] as $section) {
        $sections[$section['key']] = $section['entries'];
    }

    return $sections;
}

it('inspects a route', function (): void {
    $sections = inspectNode(inspectableHttpGraph(), 'route:DELETE:/orders/{order}');

    expect($sections['identity']['URI'])->toBe('/orders/{order}')
        ->and($sections['identity']['Name'])->toBe('orders.destroy')
        ->and($sections['action']['Controller'])->toBe(OrderController::class)
        ->and($sections['action']['Method'])->toBe('destroy')
        ->and($sections['middleware']['Middleware'])->toBe(['web', 'auth', 'verified'])
        ->and($sections['models']['Bound parameters'])->toBe(['{order}: Order']);
});

it('inspects a controller action by action', function (): void {
    $sections = inspectNode(inspectableHttpGraph(), StableIdentifier::controller(OrderController::class));

    expect($sections['actions']['store'])->toBe([
        'Route: POST /orders',
        'Validates with: StoreOrderRequest',
        'Model: Order (static)',
        'Dispatches: event OrderPlaced via PlaceOrder (tests/Fixtures/Http/Actions/PlaceOrder.php:17)',
        'Dispatches: job ShipOrder via PlaceOrder (tests/Fixtures/Http/Actions/PlaceOrder.php:18)',
    ]);
});

it('explains how to read form request rules, and shows them once enabled', function (): void {
    $id = StableIdentifier::formRequest(StoreOrderRequest::class);

    $default = inspectNode(inspectableHttpGraph(), $id);
    $enabled = inspectNode(inspectableHttpGraph(invokeFormRequestRules: true), $id);

    expect($default['validation']['Rules'])->toContain('http.form_request_rules')
        ->and($default['identity']['Used by'])->toBe(['OrderController (store)'])
        ->and($enabled['validation']['Rules'])->toBe([
            'customer_id: required|integer',
            'note: nullable|closure',
            'status: required|in:"draft","placed"',
        ]);
});

it('inspects events, listeners and dispatched jobs', function (): void {
    $graph = inspectableHttpGraph();

    $event = inspectNode($graph, StableIdentifier::event(OrderPlaced::class));
    $job = inspectNode($graph, 'job:' . StableIdentifier::normalizeClass(ShipOrder::class));

    expect($event['listeners']['Listeners'])->toBe(['SendOrderConfirmation@handle', 'UpdateInventory@handle'])
        ->and($event['dispatched_by']['Sources'])->toBe([
            'OrderController@store via PlaceOrder (tests/Fixtures/Http/Actions/PlaceOrder.php:17)',
        ])
        ->and($job['identity']['Queued'])->toBe('yes')
        ->and($job['dispatches']['Dispatches'][0])->toStartWith('job NotifyWarehouse');
});

it('adds an HTTP section to models only when the HTTP map is shown', function (): void {
    $http = inspectNode(inspectableHttpGraph(), StableIdentifier::model(Order::class));

    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $laravel = app(BuildDependencyGraph::class)->execute($snapshot, new GraphQuery(scope: GraphScope::Laravel));

    expect($http['http']['Policy'])->toBe(['OrderPolicy'])
        ->and($http['http']['Routes binding it'])->toBe([
            'DELETE /orders/{order}',
            'GET /orders/{order}',
            'PUT /orders/{order}',
        ])
        ->and($http['http']['Controllers'])->toBe(['OrderController@destroy, index, show, store, update (binding, static)'])
        ->and(inspectNode($laravel, StableIdentifier::model(Order::class)))->not->toHaveKey('http');
});

it('inspects HTTP edges', function (): void {
    $graph = inspectableHttpGraph();
    $edge = $graph->edgesOfType(EdgeType::Dispatches)[0];

    $inspection = app(EdgeInspector::class)->inspect($edge, $graph)->toArray();
    $details = collect($inspection['sections'])->firstWhere('key', 'http');

    expect($details['entries']['Type'])->toBe('dispatches')
        ->and($details['entries']['Detection'])->toBe('static');
});
