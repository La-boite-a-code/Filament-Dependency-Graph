<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewReference;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\FilamentViews\Widgets\StatsWidget;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
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
        ->and(array_map(static fn ($dynamic): string => $dynamic->expression, $map->dynamics))->toBe(['$customPartial', '$widget']);
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
