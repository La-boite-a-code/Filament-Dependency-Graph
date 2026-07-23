<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;
use LaBoiteACode\DependencyGraph\Graph\ShortestPathFinder;

it('finds the shortest path between two nodes', function (): void {
    // a -> b -> d and a -> c -> e -> d: shortest is through b.
    $graph = fakeGraph(
        [fakeNode('a'), fakeNode('b'), fakeNode('c'), fakeNode('d'), fakeNode('e')],
        [
            fakeEdge('a', 'b'),
            fakeEdge('b', 'd'),
            fakeEdge('a', 'c'),
            fakeEdge('c', 'e'),
            fakeEdge('e', 'd'),
        ],
    );

    $path = (new ShortestPathFinder)->find($graph, NodeId::fromString('a'), NodeId::fromString('d'));

    expect($path)->not->toBeNull()
        ->and($path->length())->toBe(2)
        ->and($path->toArray()['nodes'])->toBe(['a', 'b', 'd']);
});

it('returns null when nodes are disconnected', function (): void {
    $graph = fakeGraph(
        [fakeNode('a'), fakeNode('b'), fakeNode('isolated')],
        [fakeEdge('a', 'b')],
    );

    $path = (new ShortestPathFinder)->find($graph, NodeId::fromString('a'), NodeId::fromString('isolated'));

    expect($path)->toBeNull();
});

it('returns a zero length path for identical endpoints', function (): void {
    $graph = fakeGraph([fakeNode('a')]);

    $path = (new ShortestPathFinder)->find($graph, NodeId::fromString('a'), NodeId::fromString('a'));

    expect($path?->length())->toBe(0);
});

it('returns null when an endpoint is missing', function (): void {
    $graph = fakeGraph([fakeNode('a')]);

    expect((new ShortestPathFinder)->find($graph, NodeId::fromString('a'), NodeId::fromString('zz')))
        ->toBeNull();
});
