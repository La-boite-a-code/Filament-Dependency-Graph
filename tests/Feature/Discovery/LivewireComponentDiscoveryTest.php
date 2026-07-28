<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Application\BuildDependencyGraph;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\LivewireComponentDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\OrderDashboard;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;

it('discovers application Livewire components without a Filament panel', function (): void {
    $components = app(LivewireComponentDiscoverer::class)->discover($this->fixtureContext());

    expect($components)->toHaveCount(2);

    $dashboard = collect($components)->first(
        static fn ($component): bool => $component->class === OrderDashboard::class,
    );

    expect($dashboard)->not->toBeNull()
        ->and($dashboard->alias)->toBe('order-dashboard')
        ->and($dashboard->view)->toBe('livewire.order-dashboard')
        ->and($dashboard->file)->toBe('tests/Fixtures/Livewire/OrderDashboard.php')
        ->and($dashboard->publicProperties)->toBe(['order', 'search'])
        ->and($dashboard->publicMethods)->toBe(['mount', 'refreshProducts', 'render'])
        ->and(array_keys($dashboard->modelReferences))->toBe([
            Order::class,
            Product::class,
            User::class,
        ])
        ->and($dashboard->modelReferences[Order::class])->toContain('property:order')
        ->and($dashboard->modelReferences[Product::class])->toContain('source:Product')
        ->and($dashboard->modelReferences[User::class])->toContain('parameter:mount.viewer');
});

it('can disable Livewire component discovery', function (): void {
    $components = app(LivewireComponentDiscoverer::class)->discover(
        $this->fixtureContext(discoverLivewireComponents: false),
    );

    expect($components)->toBe([]);
});

it('adds Livewire components and their model dependencies to the Laravel scope', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $graph = app(BuildDependencyGraph::class)->execute(
        $snapshot,
        new GraphQuery(scope: GraphScope::Laravel),
    );

    expect($snapshot->livewireComponents)->toHaveCount(2)
        ->and($graph->nodesOfType(NodeType::LivewireComponent))->toHaveCount(2)
        ->and($graph->edgesOfType(EdgeType::LivewireUsesModel))->toHaveCount(3);
});

it('keeps standalone Livewire components outside the Filament scope', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $graph = app(BuildDependencyGraph::class)->execute(
        $snapshot,
        new GraphQuery(scope: GraphScope::Filament),
    );

    expect($graph->nodesOfType(NodeType::LivewireComponent))->toBe([])
        ->and($graph->edgesOfType(EdgeType::LivewireUsesModel))->toBe([]);
});

it('round trips Livewire components through snapshot serialization', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover($this->fixtureContext());
    $restored = ApplicationSnapshot::fromArray($snapshot->toArray());

    expect(array_map(
        static fn ($component): array => $component->toArray(),
        $restored->livewireComponents,
    ))->toBe(array_map(
        static fn ($component): array => $component->toArray(),
        $snapshot->livewireComponents,
    ));
});
