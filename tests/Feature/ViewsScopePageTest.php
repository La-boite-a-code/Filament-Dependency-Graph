<?php

declare(strict_types=1);

use Filament\Facades\Filament;
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
        'Views without a detected reference',
    ]);

    $routes = collect($tree)->firstWhere('label', 'Routes and controllers');
    $controller = collect($routes['children'])->firstWhere('label', 'OrderController');
    $index = $controller['children'][0];

    expect($index['label'])->toBe('orders.index')
        ->and(collect($index['children'])->pluck('label')->all())->toContain('layouts.app', 'orders.partials.row', 'Alert');

    // Shared templates are unfolded once for the whole tree.
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
        ->and(array_filter($occurrences, static fn (bool $shown): bool => ! $shown))->toHaveCount(1);
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
