<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Discovery\Http\ControllerDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Http\RouteDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\AccountController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\OrderItem;

/**
 * @return array<string, RouteData>
 */
function discoveredRoutesByName(mixed ...$overrides): array
{
    $routes = [];

    foreach (app(RouteDiscoverer::class)->discover(test()->fixtureContext(...$overrides)) as $route) {
        $routes[(string) $route->name] = $route;
    }

    return $routes;
}

it('keeps closure middleware serializable', function (): void {
    Route::group(['middleware' => [static fn ($request, $next) => $next($request)]], static function (): void {
        Route::get('closure-guarded', [OrderController::class, 'index'])->name('closure.guarded');
    });

    $route = discoveredRoutesByName()['closure.guarded'];

    expect($route->middleware)->toBe(['Closure'])
        ->and(serialize(app(ApplicationDiscovery::class)->discover($this->fixtureContext())))->toBeString();
});

it('expands middleware groups and pairs aliases with their classes', function (): void {
    /** @var Router $router */
    $router = app(Router::class);
    $router->aliasMiddleware('auth', Authenticate::class);
    $router->middlewareGroup('admin', ['auth', 'throttle:admin']);

    Route::middleware('admin')->get('admin/reports', [OrderController::class, 'index'])->name('admin.reports');

    $route = discoveredRoutesByName()['admin.reports'];

    expect($route->middleware)->toBe(['admin'])
        ->and($route->resolvedMiddleware)->toContain('admin', 'auth', Authenticate::class, 'throttle:admin');
});

it('reads the framework controller middleware attributes', function (): void {
    if (! class_exists('Illuminate\Routing\Attributes\Controllers\Middleware')) {
        $this->markTestSkipped('Controller middleware attributes need Laravel 13.');
    }

    Route::middleware(['web', 'throttle'])->group(static function (): void {
        Route::get('account/{order_item}', [AccountController::class, 'show'])->name('account.show');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });

    $routes = discoveredRoutesByName();

    expect($routes['account.show']->middleware)->toBe(['web', 'throttle'])
        ->and($routes['account.update']->middleware)->toBe(['web', 'verified', 'password.confirm']);
});

it('binds snake case route parameters to camel case arguments', function (): void {
    Route::get('items/{order_item}', [AccountController::class, 'show'])->name('items.show');

    expect(discoveredRoutesByName()['items.show']->boundParameters)->toBe(['order_item' => OrderItem::class]);
});

it('skips closures restored from the route cache unless vendor routes are included', function (): void {
    $route = Route::get('cached-closure', static fn (): string => 'ok')->name('cached.closure');
    $route->prepareForSerialization();

    $discoverer = app(RouteDiscoverer::class);

    $default = [];

    foreach ($discoverer->discover($this->fixtureContext()) as $data) {
        $default[] = $data->name;
    }

    $warnings = $discoverer->pullWarnings();

    $withVendor = array_map(
        static fn (RouteData $data): ?string => $data->name,
        $discoverer->discover($this->fixtureContext(includeVendorRoutes: true)),
    );

    expect($default)->not->toContain('cached.closure')
        ->and($warnings[0]->type)->toBe('route_cache_closures_skipped')
        ->and($withVendor)->toContain('cached.closure');
});

it('removes excluded middleware however it was written', function (): void {
    /** @var Router $router */
    $router = app(Router::class);
    $router->aliasMiddleware('auth', Authenticate::class);
    $router->middlewareGroup('admin', ['auth']);

    Route::middleware('auth')->get('by-class', [OrderController::class, 'index'])
        ->withoutMiddleware(Authenticate::class)
        ->name('excluded.by.class');
    Route::middleware(['admin', 'auth'])->get('by-group', [OrderController::class, 'index'])
        ->withoutMiddleware('admin')
        ->name('excluded.by.group');
    Route::middleware(['admin'])->get('kept', [OrderController::class, 'index'])->name('kept');

    $routes = discoveredRoutesByName();

    expect($routes['excluded.by.class']->middleware)->toBe([])
        ->and($routes['excluded.by.class']->resolvedMiddleware)->not->toContain('auth')
        ->and($routes['excluded.by.group']->middleware)->toBe([])
        ->and($routes['excluded.by.group']->resolvedMiddleware)->toBe([])
        ->and($routes['kept']->resolvedMiddleware)->toContain('admin', 'auth', Authenticate::class);
});

it('binds snake case parameters in controller actions too', function (): void {
    Route::get('lines/{order_item}', [AccountController::class, 'show'])->name('lines.show');

    $context = $this->fixtureContext();
    $controllers = app(ControllerDiscoverer::class)->discover(
        app(RouteDiscoverer::class)->discover($context),
        $context,
        [],
    );

    $account = collect($controllers)->firstWhere('class', AccountController::class);

    expect($account->actions['show']->models)->toBe([OrderItem::class => ['binding']]);
});
