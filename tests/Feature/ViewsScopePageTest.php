<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\StandaloneCounter;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('filament-dependency-graph.authorization.local_only', false);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('offers the Views scope and its datasets', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->assertSeeHtml('<option value="views">')
        ->set('scope', 'views')
        ->assertSet('tableDataset', 'views')
        ->call('setView', 'table')
        ->assertSee('Package views')
        ->assertSee('orders.index');
});

it('lists views, components and package views', function (): void {
    $tables = Livewire::test(DependencyGraphPage::class)->set('scope', 'views')->instance()->getTables();

    $index = collect($tables['views'])->firstWhere('label', 'orders.index');
    $row = collect($tables['views'])->firstWhere('label', 'orders.partials.row');

    expect($index['kind'])->toBe('page')
        ->and($index['used_by'])->toBe(1)
        ->and($row['used_by'])->toBe(1)
        ->and(collect($tables['components'])->pluck('label')->all())->toContain('Alert', 'Price', 'StatsWidget', 'components.badge')
        ->and(collect($tables['externals'])->firstWhere('label', 'orders.partials.missing')['missing'])->toBeTrue();
});

it('groups the Views tree by owner and lists unreferenced views', function (): void {
    $tree = Livewire::test(DependencyGraphPage::class)->set('scope', 'views')->instance()->getTree();

    expect(collect($tree)->pluck('label')->all())->toBe([
        'Routes and controllers',
        'Livewire',
        'Filament',
        'Blade components',
        'Mail',
        'Views and components without a detected reference',
    ]);

    $unreferenced = collect($tree)->firstWhere('id', 'group:unreferenced-views');

    expect(collect($unreferenced['children'])->pluck('label')->all())->toContain('unused', 'Unused');

    $routes = collect($tree)->firstWhere('label', 'Routes and controllers');
    $controller = collect($routes['children'])->firstWhere('label', 'OrderController');
    $index = $controller['children'][0];

    expect($index['label'])->toBe('orders.index')
        ->and(collect($index['children'])->pluck('label')->all())->toContain('layouts.app', 'orders.partials.row', 'Alert');

    // Shared templates are not unfolded again at the same or a lower depth.
    $occurrences = [];
    $walk = function (array $items) use (&$walk, &$occurrences): void {
        foreach ($items as $item) {
            if ($item['id'] === 'view:layouts.app') {
                $occurrences[] = $item['already_shown'];
            }

            $walk($item['children']);
        }
    };
    $walk($tree);

    expect(count($occurrences))->toBeGreaterThan(1)
        ->and($occurrences)->toContain(true);
});

it('inspects a view with what it renders and who uses it', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'views')
        ->call('selectNode', 'view:orders.partials.row')
        ->assertSee('Blade view')
        ->assertSee('orders.index: @include, @each (lines 9, 12)')
        ->assertSee('<x-shop::price> Price (line 3)');
});

it('shows where a Livewire component is used', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->set('scope', 'views')
        ->call('selectNode', StableIdentifier::livewireComponent(StandaloneCounter::class))
        ->assertSee('Used in')
        ->assertSee('layouts.app: <livewire:standalone-counter> (line 7)');
});

it('only offers node types that exist in the Views scope', function (): void {
    $options = Livewire::test(DependencyGraphPage::class)->set('scope', 'views')->instance()->getNodeTypeOptions();

    expect($options)->toHaveKeys(['view', 'blade_component', 'external_view', 'livewire_component', 'controller'])
        ->not->toHaveKey('model')
        ->not->toHaveKey('panel');
});

it('hides the Views scope when disabled', function (): void {
    config()->set('filament-dependency-graph.views.enabled', false);

    Livewire::test(DependencyGraphPage::class)
        ->assertDontSeeHtml('<option value="views">')
        ->set('scope', 'views')
        ->assertSet('scope', 'filament');
});

it('unfolds a shared template again when the depth limit cut it short', function (): void {
    $graph = fakeGraph([
        fakeNode('route', NodeType::Route, 'GET /a'),
        fakeNode('view:a', NodeType::View),
        fakeNode('view:b', NodeType::View),
        fakeNode('view:layout', NodeType::View),
        fakeNode('view:nav', NodeType::View),
        fakeNode('view:icon', NodeType::View),
        fakeNode('livewire', NodeType::LivewireComponent, 'Dashboard'),
    ], [
        fakeEdge('route', 'view:a', EdgeType::RendersView, 'Route::view'),
        fakeEdge('view:a', 'view:b', EdgeType::ViewIncludes, '@include'),
        fakeEdge('view:b', 'view:layout', EdgeType::ViewExtends, '@extends'),
        fakeEdge('view:layout', 'view:nav', EdgeType::ViewIncludes, '@include'),
        fakeEdge('view:nav', 'view:icon', EdgeType::ViewIncludes, '@include'),
        fakeEdge('livewire', 'view:layout', EdgeType::RendersView, 'layout'),
    ]);

    $tree = (fn (): array => $this->viewsTree($graph, 4))->call(app(DependencyGraphPage::class));
    $ids = [];
    $walk = function (array $items) use (&$walk, &$ids): void {
        foreach ($items as $item) {
            $ids[] = $item['id'];
            $walk($item['children']);
        }
    };
    $walk($tree);

    // Under the route, the layout is reached at the depth limit; under the
    // Livewire component it has room to show its partials.
    expect($ids)->toContain('view:icon');
});

it('keeps routes and controllers in the Views tree without the HTTP map', function (): void {
    config()->set('filament-dependency-graph.http.enabled', false);

    $tree = Livewire::test(DependencyGraphPage::class)->set('scope', 'views')->instance()->getTree();
    $routes = collect($tree)->firstWhere('id', 'group:routes');
    $controller = collect($routes['children'] ?? [])->firstWhere('label', 'OrderController');

    expect(collect($routes['children'] ?? [])->pluck('label')->all())->toContain('OrderController', 'GET /about')
        ->and($controller['children'][0]['label'] ?? null)->toBe('orders.index');
});
