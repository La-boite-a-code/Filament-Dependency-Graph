<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Discovery\Http\RouteDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\ShowDashboardController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\OrderDashboard;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

/**
 * @param  list<RouteData>  $routes
 */
function routeNamed(array $routes, string $name): ?RouteData
{
    foreach ($routes as $route) {
        if ($route->name === $name) {
            return $route;
        }
    }

    return null;
}

it('maps application routes only', function (): void {
    $routes = app(RouteDiscoverer::class)->discover($this->fixtureContext());

    expect(array_map(static fn (RouteData $route): string => $route->label(), $routes))->toBe([
        'GET /about',
        'GET /dashboard',
        'GET /health',
        'ANY /home',
        'GET /internal/debug',
        'GET /live/orders',
        'GET /orders',
        'POST /orders',
        'DELETE /orders/{order}',
        'GET /orders/{order}',
        'PUT /orders/{order}',
    ]);
});

it('reads controller actions, bound models and declared middleware', function (): void {
    $routes = app(RouteDiscoverer::class)->discover($this->fixtureContext());
    $destroy = routeNamed($routes, 'orders.destroy');

    expect($destroy)->not->toBeNull()
        ->and($destroy->id)->toBe('route:DELETE:/orders/{order}')
        ->and($destroy->actionType)->toBe(RouteData::ACTION_CONTROLLER)
        ->and($destroy->controllerClass)->toBe(OrderController::class)
        ->and($destroy->controllerMethod)->toBe('destroy')
        ->and($destroy->middleware)->toBe(['web', 'auth', 'verified'])
        ->and($destroy->boundParameters)->toBe(['order' => Order::class]);
});

it('recognizes invokable controllers, Livewire pages, views, redirects and closures', function (): void {
    $routes = app(RouteDiscoverer::class)->discover($this->fixtureContext());

    expect(routeNamed($routes, 'dashboard')->controllerClass)->toBe(ShowDashboardController::class)
        ->and(routeNamed($routes, 'dashboard')->controllerMethod)->toBe('__invoke')
        ->and(routeNamed($routes, 'live.orders')->actionType)->toBe(RouteData::ACTION_LIVEWIRE)
        ->and(routeNamed($routes, 'live.orders')->livewireClass)->toBe(OrderDashboard::class)
        ->and(routeNamed($routes, 'live.orders')->controllerClass)->toBeNull()
        ->and(routeNamed($routes, 'about')->actionType)->toBe(RouteData::ACTION_VIEW)
        ->and(routeNamed($routes, 'about')->view)->toBe('pages.about')
        ->and(routeNamed($routes, 'health')->actionType)->toBe(RouteData::ACTION_CLOSURE)
        ->and(routeNamed($routes, 'health')->file)->toBe('tests/Fixtures/Http/routes.php');
});

it('excludes routes by name and URI pattern', function (): void {
    $routes = app(RouteDiscoverer::class)->discover($this->fixtureContext(
        excludedRouteNames: ['debug.*'],
        excludedRouteUris: ['live/*', 'about'],
    ));

    expect(routeNamed($routes, 'debug.internal'))->toBeNull()
        ->and(routeNamed($routes, 'live.orders'))->toBeNull()
        ->and(routeNamed($routes, 'about'))->toBeNull()
        ->and(routeNamed($routes, 'health'))->not->toBeNull();
});

it('includes vendor routes on request', function (): void {
    $default = app(RouteDiscoverer::class)->discover($this->fixtureContext());
    $withVendor = app(RouteDiscoverer::class)->discover($this->fixtureContext(includeVendorRoutes: true));

    $vendorControllers = array_filter(
        $withVendor,
        static fn (RouteData $route): bool => str_starts_with((string) ($route->controllerClass ?? $route->livewireClass), 'Livewire\\')
            || str_starts_with((string) ($route->controllerClass ?? $route->livewireClass), 'Filament\\')
            || str_starts_with((string) ($route->controllerClass ?? $route->livewireClass), 'LaBoiteACode\\DependencyGraph\\Filament\\'),
    );

    expect(count($withVendor))->toBeGreaterThan(count($default))
        ->and($vendorControllers)->not->toBe([]);
});
