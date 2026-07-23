<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Application\SearchDependencyGraph;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;

function searchGraph(): LaBoiteACode\DependencyGraph\Domain\Graph\Graph
{
    return fakeGraph(
        [
            fakeNode('model:app.models.order', label: 'Order', metadata: [
                'class' => 'App\\Models\\Order',
                'namespace' => 'App\\Models',
                'table' => 'orders',
            ]),
            fakeNode('model:app.models.order-item', label: 'OrderItem', metadata: [
                'class' => 'App\\Models\\OrderItem',
                'namespace' => 'App\\Models',
                'table' => 'order_items',
            ]),
            fakeNode('resource:app.resources.order-resource', NodeType::Resource, 'OrderResource', [
                'class' => 'App\\Resources\\OrderResource',
                'namespace' => 'App\\Resources',
                'navigation_group' => 'Shop',
            ]),
            fakeNode('model:app.models.customer', label: 'Customer', metadata: [
                'class' => 'App\\Models\\Customer',
                'namespace' => 'App\\Models',
                'table' => 'customers',
            ]),
        ],
        [
            fakeEdge('model:app.models.order', 'model:app.models.customer', label: 'customer'),
        ],
    );
}

it('ranks an exact label match first', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'Order');

    expect($results[0]->nodeId)->toBe('model:app.models.order')
        ->and($results[0]->score)->toBe(100);
});

it('ranks prefix matches above contains matches', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'OrderIt');

    expect($results[0]->nodeId)->toBe('model:app.models.order-item');
});

it('is case insensitive', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'ORDER');

    expect($results[0]->nodeId)->toBe('model:app.models.order');
});

it('is accent insensitive', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'Ordér');

    expect($results)->not->toBeEmpty()
        ->and($results[0]->nodeId)->toBe('model:app.models.order');
});

it('matches table names exactly', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'customers');

    expect($results[0]->nodeId)->toBe('model:app.models.customer')
        ->and($results[0]->matchedField)->toBe('table');
});

it('matches namespaces through metadata', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'Resources');

    $ids = array_map(static fn ($result): string => $result->nodeId, $results);

    expect($ids)->toContain('resource:app.resources.order-resource');
});

it('matches relation method names', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'customer');

    $ids = array_map(static fn ($result): string => $result->nodeId, $results);

    expect($ids)->toContain('model:app.models.order');
});

it('matches navigation groups', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'Shop');

    expect($results[0]->nodeId)->toBe('resource:app.resources.order-resource');
});

it('returns nothing for empty queries', function (): void {
    expect((new SearchDependencyGraph)->execute(searchGraph(), '   '))->toBe([]);
});

it('respects the result limit and sorts deterministically', function (): void {
    $results = (new SearchDependencyGraph)->execute(searchGraph(), 'order', 2);

    expect($results)->toHaveCount(2);

    $again = (new SearchDependencyGraph)->execute(searchGraph(), 'order', 2);

    expect(array_map(static fn ($result): array => $result->toArray(), $results))
        ->toBe(array_map(static fn ($result): array => $result->toArray(), $again));
});
