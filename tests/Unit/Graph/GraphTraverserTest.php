<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\NodeNotFoundException;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;
use LaBoiteACode\DependencyGraph\Graph\GraphTraverser;

function traverserGraph(): LaBoiteACode\DependencyGraph\Domain\Graph\Graph
{
    // a -> b -> c -> d, plus x -> a
    return fakeGraph(
        [fakeNode('a'), fakeNode('b'), fakeNode('c'), fakeNode('d'), fakeNode('x')],
        [
            fakeEdge('a', 'b', label: 'ab'),
            fakeEdge('b', 'c', label: 'bc'),
            fakeEdge('c', 'd', label: 'cd'),
            fakeEdge('x', 'a', label: 'xa'),
        ],
    );
}

it('always includes the root node', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(traverserGraph(), NodeId::fromString('a'), 0);

    expect($neighbourhood->graph->nodeCount())->toBe(1)
        ->and($neighbourhood->graph->hasNode('a'))->toBeTrue()
        ->and($neighbourhood->depths['a'])->toBe(0);
});

it('traverses to depth one', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(traverserGraph(), NodeId::fromString('a'), 1);

    expect($neighbourhood->graph->hasNode('b'))->toBeTrue()
        ->and($neighbourhood->graph->hasNode('x'))->toBeTrue()
        ->and($neighbourhood->graph->hasNode('c'))->toBeFalse()
        ->and($neighbourhood->depths['b'])->toBe(1);
});

it('traverses to depth two', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(traverserGraph(), NodeId::fromString('a'), 2);

    expect($neighbourhood->graph->hasNode('c'))->toBeTrue()
        ->and($neighbourhood->graph->hasNode('d'))->toBeFalse()
        ->and($neighbourhood->depths['c'])->toBe(2);
});

it('supports unlimited depth', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(traverserGraph(), NodeId::fromString('a'), null);

    expect($neighbourhood->graph->nodeCount())->toBe(5);
});

it('honors the outgoing direction', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(
        traverserGraph(),
        NodeId::fromString('a'),
        null,
        TraversalDirection::Outgoing,
    );

    expect($neighbourhood->graph->hasNode('b'))->toBeTrue()
        ->and($neighbourhood->graph->hasNode('x'))->toBeFalse();
});

it('honors the incoming direction', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(
        traverserGraph(),
        NodeId::fromString('a'),
        null,
        TraversalDirection::Incoming,
    );

    expect($neighbourhood->graph->hasNode('x'))->toBeTrue()
        ->and($neighbourhood->graph->hasNode('b'))->toBeFalse();
});

it('handles cycles without infinite loops', function (): void {
    $graph = fakeGraph(
        [fakeNode('a'), fakeNode('b')],
        [
            fakeEdge('a', 'b', label: 'ab'),
            fakeEdge('b', 'a', label: 'ba'),
        ],
    );

    $neighbourhood = (new GraphTraverser)->focus($graph, NodeId::fromString('a'), null);

    expect($neighbourhood->graph->nodeCount())->toBe(2)
        ->and($neighbourhood->graph->edgeCount())->toBe(2);
});

it('handles self referencing edges', function (): void {
    $graph = fakeGraph(
        [fakeNode('a')],
        [fakeEdge('a', 'a', label: 'self')],
    );

    $neighbourhood = (new GraphTraverser)->focus($graph, NodeId::fromString('a'), null);

    expect($neighbourhood->graph->edgeCount())->toBe(1);
});

it('applies edge predicates before traversal', function (): void {
    $neighbourhood = (new GraphTraverser)->focus(
        traverserGraph(),
        NodeId::fromString('a'),
        null,
        TraversalDirection::Both,
        static fn ($edge): bool => $edge->label !== 'bc',
    );

    expect($neighbourhood->graph->hasNode('b'))->toBeTrue()
        ->and($neighbourhood->graph->hasNode('c'))->toBeFalse();
});

it('throws when the root node does not exist', function (): void {
    (new GraphTraverser)->focus(traverserGraph(), NodeId::fromString('missing'));
})->throws(NodeNotFoundException::class);

it('produces stable output across runs', function (): void {
    $traverser = new GraphTraverser;

    $first = $traverser->focus(traverserGraph(), NodeId::fromString('a'), 2)->toArray();
    $second = $traverser->focus(traverserGraph(), NodeId::fromString('a'), 2)->toArray();

    expect($first)->toBe($second);
});
