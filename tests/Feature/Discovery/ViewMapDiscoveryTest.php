<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Discovery\Http\RouteDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Views\BladeTemplateScanner;
use LaBoiteACode\DependencyGraph\Discovery\Views\ViewOwnerDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Views\ViewReferenceResolver;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewOwnerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewReference;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Graph\NodeFactory;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\FilamentViews\Widgets\StatsWidget;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\StandaloneCounter;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Alert;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\ViewOwners\ProvidedViewComponent;

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

    config()->set('livewire.component_layout', 'layouts.app');
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
        ->and($owners[$routes['single-file-counter']->id])->toBe(['Route::livewire view:livewire.counter', 'layout view:layouts.app']);
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

it('gives routed full-page components the configured layout', function (): void {
    config()->set('livewire.component_layout', null);
    config()->set('livewire.layout', null);
    // Livewire 4 reads component_layout, Livewire 3 reads layout.
    config()->set(app()->bound('livewire.finder') ? 'livewire.component_layout' : 'livewire.layout', 'layouts.app');
    Route::get('counter-page', StandaloneCounter::class);
    Route::getRoutes()->refreshNameLookups();

    $owners = [];

    foreach (viewMap()->owners as $owner) {
        $owners[$owner->class ?? $owner->id] = array_map(static fn ($rendered): string => $rendered->how . ' ' . $rendered->name, $owner->renders);
    }

    expect($owners[StandaloneCounter::class])->toBe(['render livewire.standalone-counter', 'layout layouts.app']);
});

it('points a Filament widget embedded with @livewire to its Filament node', function (): void {
    $map = viewMap();
    $host = viewsByName($map)['pages.about'];

    expect(array_map(static fn ($reference): ?string => $reference->targetId, $host->references))
        ->toContain(StableIdentifier::filamentComponent(StatsWidget::class))
        ->and(array_map(static fn ($external): string => $external->reference, $map->externals))
        ->not->toContain(StatsWidget::class);
});

it('names anonymous components by the prefix the application chose', function (): void {
    $path = dirname(__DIR__, 2) . '/Fixtures/views-anonymous';
    Blade::anonymousComponentPath($path . '/kit', 'kit');
    app('view')->getFinder()->addLocation($path . '/host');

    $externals = array_map(static fn ($external): string => $external->reference, viewMap()->externals);
    $explored = viewsByName(viewMap(explorePackageViews: true));

    expect($externals)->toContain('kit::button')
        ->and(array_filter($externals, static fn (string $reference): bool => preg_match('/^[0-9a-f]{32}::/', $reference) === 1))->toBe([])
        ->and($explored)->toHaveKey('kit::button')
        ->and(array_filter(array_keys($explored), static fn (string $name): bool => preg_match('/^[0-9a-f]{32}::/', $name) === 1))->toBe([]);
});

it('reads the layout declared by routed single and multi-file components', function (): void {
    if (! Route::hasMacro('livewire')) {
        $this->markTestSkipped('Route::livewire() needs Livewire 4.');
    }

    $path = dirname(__DIR__, 2) . '/Fixtures/views-livewire4';
    app('view')->getFinder()->addLocation($path);
    app('livewire.finder')->addLocation(viewPath: $path . '/livewire');
    config()->set('livewire.component_layout', 'partials.default-nav');

    Route::livewire('with-layout', 'with-layout');
    Route::livewire('panel-page', 'panel');
    Route::livewire('counter-page', 'counter');

    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $renders = [];

    foreach ($snapshot->views->owners as $owner) {
        if ($owner->ownerType === ViewOwnerData::TYPE_ROUTE) {
            $renders[$owner->label] = array_map(static fn ($rendered): string => $rendered->how . ' ' . $rendered->targetId, $owner->renders);
        }
    }

    expect($renders)->toMatchArray([
        // The commented-out attribute is ignored.
        'GET /with-layout' => ['Route::livewire view:livewire.with-layout', 'layout view:layouts.app'],
        // Several attributes in one group, declared in the class file.
        'GET /panel-page' => ['Route::livewire view:livewire.panel.panel', 'layout view:pages.about'],
        // Nothing declared: the configured layout.
        'GET /counter-page' => ['Route::livewire view:livewire.counter', 'layout view:partials.default-nav'],
    ]);
});

it('keeps single-file components of vendor packages out of the routes', function (): void {
    if (! Route::hasMacro('livewire')) {
        $this->markTestSkipped('Route::livewire() needs Livewire 4.');
    }

    $path = dirname(__DIR__, 2) . '/Fixtures/views-livewire4';
    app('livewire.finder')->addLocation(viewPath: $path . '/livewire');
    Route::livewire('packaged-counter', 'counter');

    $uris = static fn (DiscoveryContext $context): array => array_map(
        static fn (RouteData $route): string => $route->uri,
        app(RouteDiscoverer::class)->discover($context),
    );

    // The fixture folder stands for a vendor package here.
    expect($uris($this->fixtureContext(vendorPath: $path)))->not->toContain('packaged-counter')
        ->and($uris($this->fixtureContext(vendorPath: $path, includeVendorRoutes: true)))->toContain('packaged-counter')
        ->and($uris($this->fixtureContext()))->toContain('packaged-counter');
});

it('reads the view Livewire 4 components provide through view()', function (): void {
    $context = $this->fixtureContext();
    $resolver = app(ViewReferenceResolver::class);
    $resolver->prepare(app(BladeTemplateScanner::class)->templates($context), $context);

    $component = new LivewireComponentData(
        id: StableIdentifier::livewireComponent(ProvidedViewComponent::class),
        class: ProvidedViewComponent::class,
        shortName: 'ProvidedViewComponent',
        namespace: 'LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\ViewOwners',
        alias: 'provided-view-component',
        view: null,
        file: null,
        publicProperties: [],
        publicMethods: ['view'],
        modelReferences: [],
        status: DiscoveryStatus::Complete,
        warnings: [],
    );

    $owner = collect(app(ViewOwnerDiscoverer::class)->discover($context, $resolver, [$component], [], new HttpMapData))
        ->firstWhere('class', ProvidedViewComponent::class);

    // On Livewire 3, view() is an ordinary method: nothing is rendered.
    expect($owner?->renders[0]->name ?? null)->toBe(app()->bound('livewire.finder') ? 'partials.nav' : null);
});

it('describes routes rendering views when the HTTP map does not', function (): void {
    $node = app(NodeFactory::class)->forViewOwner(new ViewOwnerData(
        id: 'route:get:home',
        ownerType: ViewOwnerData::TYPE_ROUTE,
        class: null,
        label: 'GET /',
        detail: 'home',
        file: null,
        renders: [],
        status: DiscoveryStatus::Complete,
        warnings: [],
    ));

    expect($node->type)->toBe(NodeType::Route)
        ->and($node->metadata)->toMatchArray(['methods' => ['GET'], 'uri' => '/', 'name' => 'home']);
});
