<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentVersion;
use LaBoiteACode\DependencyGraph\Contracts\PanelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\ResourceDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\RelationManagers\ItemsRelationManager;

/**
 * @return array<string, PanelData>
 */
function discoveredPanels(mixed ...$overrides): array
{
    $panels = [];

    foreach (app(PanelDiscoverer::class)->discover(test()->fixtureContext(...$overrides)) as $panel) {
        $panels[$panel->id] = $panel;
    }

    return $panels;
}

/**
 * @return array<string, ResourceData> Keyed by short name.
 */
function discoveredResources(mixed ...$overrides): array
{
    $resources = [];

    foreach (app(ResourceDiscoverer::class)->discover(test()->fixtureContext(...$overrides)) as $resource) {
        $resources[$resource->shortName] = $resource;
    }

    return $resources;
}

it('discovers panel ids, paths and domains', function (): void {
    $panels = discoveredPanels();

    expect($panels)->toHaveKeys(['admin', 'operations', 'customer'])
        ->and($panels['admin']->path)->toBe('admin')
        ->and($panels['customer']->domain)->toBe('customers.example.test');
});

it('filters panels through the discovery context', function (): void {
    $panels = discoveredPanels(panelIds: ['admin']);

    expect($panels)->toHaveKey('admin')
        ->and($panels)->not->toHaveKey('operations');
});

it('links panels to their registered resources', function (): void {
    $panels = discoveredPanels();

    expect($panels['admin']->resourceIds)->toContain(
        'resource:la-boite-a-code.dependency-graph.tests.fixtures.resources.order-resource',
    )->and(count($panels['admin']->resourceIds))->toBe(4);
});

it('discovers resources with their model link', function (): void {
    $resources = discoveredResources();

    expect($resources)->toHaveKeys(['CustomerResource', 'OrderResource', 'ProductResource', 'UserResource', 'BrokenResource'])
        ->and($resources['UserResource']->modelClass)->toBe(User::class)
        ->and($resources['UserResource']->modelId)
        ->toBe('model:la-boite-a-code.dependency-graph.tests.fixtures.models.user');
});

it('discovers resource labels and navigation metadata', function (): void {
    $resources = discoveredResources();

    expect($resources['CustomerResource']->label)->not->toBe('')
        ->and($resources['CustomerResource']->pluralLabel)->not->toBe('')
        ->and($resources['CustomerResource']->navigationGroup)->toBe('Shop')
        ->and($resources['CustomerResource']->navigationIcon)->toBe('heroicon-o-users');
});

it('discovers resource pages with their types', function (): void {
    $pages = [];

    foreach (discoveredResources()['CustomerResource']->pages as $page) {
        $pages[$page->name] = $page;
    }

    expect($pages)->toHaveKeys(['index', 'create', 'edit'])
        ->and($pages['index']->type)->toBe('list')
        ->and($pages['create']->type)->toBe('create')
        ->and($pages['edit']->type)->toBe('edit');
});

it('discovers relation managers with relationship names', function (): void {
    $managers = discoveredResources()['OrderResource']->relationManagers;

    expect($managers)->toHaveCount(1)
        ->and($managers[0]->class)->toBe(ItemsRelationManager::class)
        ->and($managers[0]->relationship)->toBe('items')
        ->and($managers[0]->title)->toBe('Order items');
});

it('keeps one resource entity registered in multiple panels', function (): void {
    $user = discoveredResources()['UserResource'];

    expect($user->panelIds)->toBe(['admin', 'operations']);
});

it('marks resources with invalid models as partial', function (): void {
    $broken = discoveredResources()['BrokenResource'];

    expect($broken->modelId)->toBeNull()
        ->and($broken->status)->toBe(DiscoveryStatus::Partial)
        ->and($broken->warnings)->not->toBeEmpty();
});

it('uses the adapter matching the installed Filament major version', function (): void {
    $adapter = app(FilamentAdapter::class);

    expect($adapter->version())->toBe(FilamentVersion::detect());
});
