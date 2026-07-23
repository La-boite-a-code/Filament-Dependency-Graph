<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Graph\CycleDetector;

it('detects a self cycle', function (): void {
    $graph = fakeGraph(
        [fakeNode('a')],
        [fakeEdge('a', 'a', label: 'self')],
    );

    expect((new CycleDetector)->detect($graph))->toBe([['a']]);
});

it('detects a multi node cycle', function (): void {
    $graph = fakeGraph(
        [fakeNode('user'), fakeNode('team'), fakeNode('other')],
        [
            fakeEdge('user', 'team', label: 'team'),
            fakeEdge('team', 'user', label: 'owner'),
            fakeEdge('other', 'user', label: 'user'),
        ],
    );

    expect((new CycleDetector)->detect($graph))->toBe([['team', 'user']]);
});

it('does not report acyclic graphs as cyclic', function (): void {
    $graph = fakeGraph(
        [fakeNode('a'), fakeNode('b'), fakeNode('c')],
        [
            fakeEdge('a', 'b'),
            fakeEdge('b', 'c'),
        ],
    );

    expect((new CycleDetector)->detect($graph))->toBe([]);
});

it('only considers model relation edges', function (): void {
    $graph = fakeGraph(
        [
            fakeNode('resource:a', LaBoiteACode\DependencyGraph\Domain\Enums\NodeType::Resource),
            fakeNode('model:a'),
        ],
        [
            fakeEdge('resource:a', 'model:a', LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType::ResourceUsesModel),
            fakeEdge('model:a', 'resource:a', LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType::ResourceUsesModel),
        ],
    );

    expect((new CycleDetector)->detect($graph))->toBe([]);
});

it('reports multiple cycle groups deterministically', function (): void {
    $graph = fakeGraph(
        [fakeNode('a'), fakeNode('b'), fakeNode('x'), fakeNode('y')],
        [
            fakeEdge('a', 'b'),
            fakeEdge('b', 'a'),
            fakeEdge('x', 'y'),
            fakeEdge('y', 'x'),
        ],
    );

    expect((new CycleDetector)->detect($graph))->toBe([['a', 'b'], ['x', 'y']]);
});
