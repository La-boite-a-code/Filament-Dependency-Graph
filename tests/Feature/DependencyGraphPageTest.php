<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Clusters\ToolsCluster;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('filament-dependency-graph.authorization.local_only', false);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the page with native filament controls', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->assertOk()
        ->assertSeeHtml('fi-checkbox-input')
        ->assertSeeHtml('fi-tabs')
        ->assertSeeHtml('fi-select-input')
        ->assertSeeHtml('fi-btn');
});

it('renders the interactive graph canvas by default', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->assertOk()
        ->assertSeeHtml('fdg-graph-container');
});

it('switches views and keeps rendering', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->call('setView', 'tree')
        ->assertOk()
        ->call('setView', 'table')
        ->assertOk();
});

it('toggles node types from the explorer checkboxes', function (): void {
    Livewire::test(DependencyGraphPage::class)
        ->call('toggleNodeType', 'panel')
        ->assertOk()
        ->assertSet('hiddenNodeTypes', ['panel'])
        ->call('toggleNodeType', 'panel')
        ->assertSet('hiddenNodeTypes', []);
});

it('renders full width by default', function (): void {
    $page = Livewire::test(DependencyGraphPage::class)->instance();

    expect($page->getMaxContentWidth())->toBe(Width::Full);
});

it('reads navigation and page customization from the configuration', function (): void {
    config()->set('filament-dependency-graph.navigation.label', 'Architecture map');
    config()->set('filament-dependency-graph.navigation.icon', 'heroicon-o-map');
    config()->set('filament-dependency-graph.navigation.group', 'Architecture');
    config()->set('filament-dependency-graph.navigation.sort', 7);
    config()->set('filament-dependency-graph.page.slug', 'architecture-map');
    config()->set('filament-dependency-graph.page.max_content_width', '7xl');

    expect(DependencyGraphPage::getNavigationLabel())->toBe('Architecture map')
        ->and(DependencyGraphPage::getNavigationIcon())->toBe('heroicon-o-map')
        ->and(DependencyGraphPage::getNavigationGroup())->toBe('Architecture')
        ->and(DependencyGraphPage::getNavigationSort())->toBe(7)
        ->and(DependencyGraphPage::getSlug())->toBe('architecture-map');

    $page = Livewire::test(DependencyGraphPage::class)->instance();

    expect($page->getMaxContentWidth())->toBe(Width::SevenExtraLarge);
});

it('lets the plugin override navigation and page options fluently', function (): void {
    DependencyGraphPlugin::current()
        ?->navigationLabel('Graph')
        ->navigationIcon('heroicon-o-cube')
        ->activeNavigationIcon('heroicon-s-cube')
        ->navigationGroup(fn (): string => 'Dev tools')
        ->navigationSort(3)
        ->navigationParentItem('Tools')
        ->navigationBadge(fn (): int => 42)
        ->slug('graph')
        ->cluster(ToolsCluster::class)
        ->maxContentWidth(Width::ScreenTwoExtraLarge);

    expect(DependencyGraphPage::getNavigationLabel())->toBe('Graph')
        ->and(DependencyGraphPage::getNavigationIcon())->toBe('heroicon-o-cube')
        ->and(DependencyGraphPage::getActiveNavigationIcon())->toBe('heroicon-s-cube')
        ->and(DependencyGraphPage::getNavigationGroup())->toBe('Dev tools')
        ->and(DependencyGraphPage::getNavigationSort())->toBe(3)
        ->and(DependencyGraphPage::getNavigationParentItem())->toBe('Tools')
        ->and(DependencyGraphPage::getNavigationBadge())->toBe('42')
        ->and(DependencyGraphPage::getSlug())->toBe('graph')
        ->and(DependencyGraphPage::getCluster())->toBe(ToolsCluster::class);
});

it('hides the navigation item when registration is disabled', function (): void {
    expect(DependencyGraphPage::shouldRegisterNavigation())->toBeTrue();

    DependencyGraphPlugin::current()?->registerNavigation(false);

    expect(DependencyGraphPage::shouldRegisterNavigation())->toBeFalse();
});
