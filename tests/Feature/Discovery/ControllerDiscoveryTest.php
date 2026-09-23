<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Discovery\Http\ControllerDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Http\FormRequestDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Http\RouteDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\ShowDashboardController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests\StoreOrderRequest;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests\UpdateOrderRequest;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;

/**
 * @return list<ControllerData>
 */
function discoverFixtureControllers(mixed ...$overrides): array
{
    $context = test()->fixtureContext(...$overrides);
    $routes = app(RouteDiscoverer::class)->discover($context);

    return app(ControllerDiscoverer::class)->discover($routes, $context, []);
}

it('describes every routed controller method', function (): void {
    $controllers = discoverFixtureControllers();

    expect(array_map(static fn (ControllerData $controller): string => $controller->class, $controllers))->toBe([
        OrderController::class,
        ShowDashboardController::class,
    ]);

    $orders = $controllers[0];

    expect(array_keys($orders->actions))->toBe(['destroy', 'index', 'show', 'store', 'update'])
        ->and($orders->file)->toBe('tests/Fixtures/Http/Controllers/OrderController.php')
        ->and($orders->status)->toBe(DiscoveryStatus::Complete)
        ->and($controllers[1]->actions['__invoke']->models)->toBe([Product::class => ['static']]);
});

it('distinguishes bound, typed and statically referenced models', function (): void {
    $orders = discoverFixtureControllers()[0];

    expect($orders->actions['show']->models)->toBe([Order::class => ['binding']])
        ->and($orders->actions['index']->models)->toBe([Order::class => ['static']])
        ->and($orders->actions['update']->models)->toBe([
            Customer::class => ['static'],
            Order::class => ['binding'],
        ])
        ->and($orders->actions['store']->models)->toBe([Order::class => ['static']]);
});

it('lists the form requests each action validates with', function (): void {
    $orders = discoverFixtureControllers()[0];

    expect($orders->actions['store']->formRequests)->toBe([StoreOrderRequest::class])
        ->and($orders->actions['update']->formRequests)->toBe([UpdateOrderRequest::class])
        ->and($orders->actions['show']->formRequests)->toBe([]);
});

it('keeps the dispatches found in actions and followed collaborators', function (): void {
    $orders = discoverFixtureControllers()[0];

    expect(count($orders->actions['store']->dispatches))->toBe(2)
        ->and(count($orders->actions['update']->dispatches))->toBe(2)
        ->and(count($orders->actions['destroy']->dispatches))->toBe(1);
});

it('describes form requests without reading their rules by default', function (): void {
    $requests = app(FormRequestDiscoverer::class)->discover(
        [StoreOrderRequest::class, UpdateOrderRequest::class],
        $this->fixtureContext(),
    );

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->class)->toBe(StoreOrderRequest::class)
        ->and($requests[0]->hasAuthorize)->toBeTrue()
        ->and($requests[0]->hasRules)->toBeTrue()
        ->and($requests[0]->rules)->toBeNull()
        ->and($requests[1]->hasAuthorize)->toBeFalse()
        ->and($requests[1]->status)->toBe(DiscoveryStatus::Complete);
});

it('reads and normalizes rules when enabled, degrading on failure', function (): void {
    $requests = app(FormRequestDiscoverer::class)->discover(
        [StoreOrderRequest::class, UpdateOrderRequest::class],
        $this->fixtureContext(invokeFormRequestRules: true),
    );

    expect($requests[0]->rules)->toBe([
        'customer_id' => ['required', 'integer'],
        'note' => ['nullable', 'closure'],
        'status' => ['required', 'in:"draft","placed"'],
    ])
        ->and($requests[0]->status)->toBe(DiscoveryStatus::Complete)
        ->and($requests[1]->rules)->toBeNull()
        ->and($requests[1]->status)->toBe(DiscoveryStatus::Partial)
        ->and($requests[1]->warnings[0])->toStartWith('rules() could not be read outside of a request');
});

it('marks unknown form requests as failed', function (): void {
    $requests = app(FormRequestDiscoverer::class)->discover(['App\Http\Requests\Missing'], $this->fixtureContext());

    expect($requests[0]->status)->toBe(DiscoveryStatus::Failed);
});
