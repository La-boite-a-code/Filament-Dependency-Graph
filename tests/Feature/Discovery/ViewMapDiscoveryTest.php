<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewReference;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\FilamentViews\Widgets\StatsWidget;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\StandaloneCounter;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Alert;

function viewMap(mixed ...$overrides): ViewMapData
{
    return app(ApplicationDiscovery::class)->discover(test()->fixtureContext(...$overrides))->views;
}

/**
 * @return array<string, ViewData>
 */
function viewsByName(ViewMapData $map): array
{
    $views = [];

    foreach ($map->views as $view) {
        $views[$view->name] = $view;
    }

    return $views;
}

it('lists every application template with its inferred kind', function (): void {
    $kinds = array_map(static fn (ViewData $view): string => $view->kind, viewsByName(viewMap()));

    expect($kinds)->toBe([
        'components.alert' => 'component',
        'components.badge' => 'component',
        'components.shop.price' => 'component',
        'filament.widgets.stats' => 'partial',
        'layouts.app' => 'layout',
        'livewire.order-dashboard' => 'livewire',
        'livewire.standalone-counter' => 'livewire',
        'mail.order-shipped' => 'mail',
        'mail.order-updated' => 'mail',
        'orders.index' => 'page',
        'orders.partials.empty' => 'partial',
        'orders.partials.row' => 'partial',
        'pages.about' => 'page',
        'partials.default-nav' => 'partial',
        'partials.nav' => 'partial',
        'unused' => 'partial',
    ]);
});

it('resolves the references of a template', function (): void {
    $index = viewsByName(viewMap())['orders.index'];

    expect(array_map(
        static fn (ViewReference $reference): string => sprintf('%d %s %s', $reference->line, $reference->directive, $reference->targetId),
        $index->references,
    ))->toBe([
        '1 @extends view:layouts.app',
        '5 <x-alert> blade-component:la-boite-a-code.dependency-graph.tests.fixtures.view.components.alert',
        '6 <x-filament::badge> external-view:filament::components.badge',
        '9 @include view:orders.partials.row',
        '12 @each view:orders.partials.row',
        '12 @each view:orders.partials.empty',
        '13 @include dynamic-view:orders.index:13',
        '14 @includeIf external-view:orders.partials.missing',
        '15 <x-dynamic-component> dynamic-view:orders.index:15',
    ]);
});

it('collects package views, missing views and dynamic references', function (): void {
    $map = viewMap();
    $externals = [];

    foreach ($map->externals as $external) {
        $externals[$external->reference] = $external->missing ? 'missing' : (string) $external->package;
    }

    expect($externals)->toMatchArray([
        'filament::components.badge' => 'filament',
        'orders.partials.missing' => 'missing',
        'partials.custom-nav' => 'missing',
        'mail::message' => 'mail',
    ])
        ->and(array_map(static fn ($dynamic): string => $dynamic->id . ' ' . $dynamic->expression, $map->dynamics))->toBe([
            'dynamic-view:orders.index:13 $customPartial',
            'dynamic-view:orders.index:15 $widget',
            // Two dynamic names on one line keep distinct identifiers.
            'dynamic-view:unused:3 $first',
            'dynamic-view:unused:3.2 $second',
        ]);
});

it('finds every owner and the views it renders', function (): void {
    $owners = [];

    foreach (viewMap()->owners as $owner) {
        $owners[$owner->label] = [
            $owner->ownerType,
            array_map(static fn ($rendered): string => $rendered->how . ' ' . $rendered->name, $owner->renders),
        ];
    }

    expect($owners)->toMatchArray([
        'OrderDashboard' => ['livewire', ['render livewire.order-dashboard', 'layout layouts.app']],
        // No render(): the view named after the component.
        'StandaloneCounter' => ['livewire', ['render livewire.standalone-counter']],
        'Alert' => ['blade_component', ['render components.alert']],
        'Price' => ['blade_component', ['render components.shop.price']],
        'Unused' => ['blade_component', []],
        'StatsWidget' => ['filament', ['$view filament.widgets.stats']],
        'Reports' => ['filament', ['getView pages.about']],
        'OrderShippedMail' => ['mailable', ['markdown mail.order-shipped']],
        'OrderUpdated' => ['notification', ['view mail.order-updated']],
        'GET /about' => ['route', ['Route::view pages.about']],
        'OrderController' => ['controller', ['view orders.index']],
    ]);
});

it('keeps the owner details used by the graph', function (): void {
    $owners = [];

    foreach (viewMap()->owners as $owner) {
        $owners[$owner->class ?? $owner->id] = $owner;
    }

    expect($owners[Alert::class]->detail)->toBe('<x-alert>')
        ->and($owners[StatsWidget::class]->detail)->toBe('Widget')
        ->and($owners[OrderController::class]->renders[0]->method)->toBe('index');
});

it('never instantiates components, widgets or controllers', function (): void {
    Alert::$instances = 0;
    StatsWidget::$instances = 0;
    OrderController::$instances = 0;

    viewMap();

    expect(Alert::$instances)->toBe(0)
        ->and(StatsWidget::$instances)->toBe(0)
        ->and(OrderController::$instances)->toBe(0);
});

it('skips the view map when disabled and honours exclusions', function (): void {
    expect(viewMap(discoverViews: false)->views)->toBe([])
        ->and(array_keys(viewsByName(viewMap(excludedViews: ['mail.*', 'unused']))))->not->toContain('mail.order-shipped', 'unused');
});

it('round trips the view map through the snapshot format', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());

    expect(ApplicationSnapshot::fromArray($snapshot->toArray())->toArray())->toBe($snapshot->toArray())
        ->and($snapshot->http->controllers[0]->actions['index']->views)->toBe(['orders.index']);
});

it('maps Livewire 4 single-file components as Livewire views', function (): void {
    if (! app()->bound('livewire.finder')) {
        $this->markTestSkipped('Single-file components need Livewire 4.');
    }

    $path = dirname(__DIR__, 2) . '/Fixtures/views-livewire4';
    app('view')->getFinder()->addLocation($path);
    app('livewire.finder')->addLocation(viewPath: $path . '/livewire');

    $views = viewsByName(viewMap());

    expect($views['livewire.counter']->kind)->toBe(ViewData::KIND_LIVEWIRE)
        ->and($views['livewire.host']->references[0]->targetId)->toBe('view:livewire.counter')
        ->and($views['livewire.counter']->references[0]->targetId)->toBe('view:components.badge');
});

it('classifies views rendered by routes and controllers when the HTTP map is off', function (): void {
    $views = viewsByName(viewMap(discoverHttp: false));

    expect($views['orders.index']->kind)->toBe(ViewData::KIND_PAGE)
        ->and($views['pages.about']->kind)->toBe(ViewData::KIND_PAGE);
});

it('reads the package views owners render when package views are explored', function (): void {
    Route::view('package-page', 'filament-dependency-graph::page');
    Route::getRoutes()->refreshNameLookups();

    expect(viewsByName(viewMap(explorePackageViews: true)))->toHaveKey('filament-dependency-graph::page')
        ->and(viewsByName(viewMap()))->not->toHaveKey('filament-dependency-graph::page');
});

it('maps components routed with Route::livewire()', function (): void {
    if (! Route::hasMacro('livewire')) {
        $this->markTestSkipped('Route::livewire() needs Livewire 4.');
    }

    $path = dirname(__DIR__, 2) . '/Fixtures/views-livewire4';
    app('view')->getFinder()->addLocation($path);
    app('livewire.finder')->addLocation(viewPath: $path . '/livewire');

    Route::livewire('counter-page', StandaloneCounter::class);
    Route::livewire('single-file-counter', 'counter');

    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $routes = [];

    foreach ($snapshot->http->routes as $route) {
        $routes[$route->uri] = $route;
    }

    $owners = [];

    foreach ($snapshot->views->owners as $owner) {
        $owners[$owner->id] = array_map(static fn ($rendered): string => $rendered->how . ' ' . $rendered->targetId, $owner->renders);
    }

    expect($routes['counter-page'])
        ->actionType->toBe(RouteData::ACTION_LIVEWIRE)
        ->livewireClass->toBe(StandaloneCounter::class)
        ->and($routes['single-file-counter'])
        ->actionType->toBe(RouteData::ACTION_LIVEWIRE)
        ->livewireClass->toBeNull()
        ->livewireComponent->toBe('counter')
        ->and($owners[$routes['single-file-counter']->id])->toBe(['Route::livewire view:livewire.counter']);
});

it('never compiles templates, so custom directives and precompilers never run', function (): void {
    $calls = 0;
    Blade::precompiler(function (string $value) use (&$calls): string {
        $calls++;

        return $value;
    });
    Blade::directive('customDirective', function () use (&$calls): string {
        $calls++;

        return '';
    });

    viewMap();

    expect($calls)->toBe(0);
});
