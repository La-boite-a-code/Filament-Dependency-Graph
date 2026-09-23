<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('filament-dependency-graph.authorization.local_only', false);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('offers the HTTP scope and its datasets', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->assertSeeHtml('<option value="http">')
        ->set('scope', 'http')
        ->assertSet('tableDataset', 'routes')
        ->call('setView', 'table')
        ->assertSee('Routes')
        ->assertSee('Dispatches')
        ->assertSee('POST /orders')
        ->assertSee('OrderController@store');
});

it('lists every route with its action, middleware, requests and models', function (): void {
    $routes = Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'http')
        ->instance()
        ->getTables()['routes'];

    $store = collect($routes)->firstWhere('label', 'POST /orders');

    // The page discovers with the testbench base path: the fixture closures
    // live outside of it and are therefore not application routes here.
    expect(collect($routes)->pluck('label')->all())->toContain('POST /orders', 'GET /about', 'ANY /home')
        ->not->toContain('GET /health')
        ->and($store['action'])->toBe('OrderController@store')
        ->and($store['middleware'])->toBe('web, auth')
        ->and($store['form_requests'])->toBe('StoreOrderRequest')
        ->and($store['models'])->toBe('Order')
        ->and(collect($routes)->firstWhere('label', 'GET /about')['action'])->toBe('View');
});

it('lists events and dispatched classes', function (): void {
    $tables = Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'http')
        ->instance()
        ->getTables();

    $archived = collect($tables['events'])->firstWhere('label', 'OrderArchived');
    $ship = collect($tables['dispatches'])->firstWhere('label', 'ShipOrder');

    expect($archived['dispatched_by'])->toBe('')
        ->and($archived['queued_listeners'])->toBe(1)
        ->and($ship['kind'])->toBe('job')
        ->and($ship['queued'])->toBeTrue()
        ->and($ship['dispatched_by'])->toBe('OrderController');
});

it('groups the HTTP tree by URI segment and lists undispatched events', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'http')
        ->call('setView', 'tree')
        ->assertSeeHtml('fdg-tree-group')
        ->assertSee('/orders')
        ->assertSee('/dashboard')
        ->assertSee('Events without a detected dispatcher');
});

it('filters routes by middleware', function (): void {
    $page = Livewire::test(DependencyGraphPage::class)->set('scope', 'http');

    $all = $page->instance()->getGraphPayload()['stats']['nodes'];

    $page->assertSee('Routes using auth')
        ->set('middlewareFilter', 'auth');

    expect($page->instance()->getGraphPayload()['stats']['nodes'])->toBeLessThan($all)
        ->and($page->instance()->getMiddlewareOptions())->toBe(['auth', 'verified', 'web']);

    $page->set('scope', 'laravel')->assertSet('middlewareFilter', '');
});

it('inspects a controller from the HTTP scope', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'http')
        ->call('selectNode', StableIdentifier::controller(OrderController::class))
        ->assertSee('Controller')
        ->assertSee('Validates with: StoreOrderRequest');
});

it('hides the HTTP scope when disabled', function (): void {
    config()->set('filament-dependency-graph.http.enabled', false);

    Livewire::test(DependencyGraphPage::class)
        ->assertDontSeeHtml('<option value="http">')
        ->set('scope', 'http')
        ->assertSet('scope', 'filament')
        ->assertSet('tableDataset', 'models');
});

it('only offers node types that exist in the current scope', function (): void {
    $page = Livewire::test(DependencyGraphPage::class);

    expect($page->instance()->getNodeTypeOptions())->not->toHaveKey('route')
        ->toHaveKey('panel');

    $page->set('scope', 'http');

    expect($page->instance()->getNodeTypeOptions())->toHaveKey('route')
        ->toHaveKey('model')
        ->not->toHaveKey('panel');
});

it('expands only the action a route calls in the HTTP tree', function (): void {
    $tree = Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'http')
        ->instance()
        ->getTree();

    $orders = collect($tree)->firstWhere('label', '/orders');
    $show = collect($orders['children'])->firstWhere('label', 'GET /orders/{order}');
    $store = collect($orders['children'])->firstWhere('label', 'POST /orders');

    $labels = static function (array $item) use (&$labels): array {
        return [$item['label'], ...collect($item['children'])->flatMap($labels)->all()];
    };

    expect($labels($show))->toBe(['GET /orders/{order}', 'OrderController', 'Order', 'OrderPolicy'])
        ->and($labels($store))->toContain('StoreOrderRequest', 'OrderPlaced', 'SendOrderConfirmation', 'ShipOrder', 'NotifyWarehouse')
        ->and($labels($store))->not->toContain('UpdateOrderRequest', 'ArchiveOrder', 'Customer');
});
