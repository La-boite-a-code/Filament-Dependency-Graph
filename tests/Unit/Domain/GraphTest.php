<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\NodeNotFoundException;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;

it('exposes nodes and edges by identifier', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:a'), fakeNode('model:b')],
        [fakeEdge('model:a', 'model:b', label: 'related', id: 'edge:1')],
    );

    expect($graph->node('model:a')?->label)->toBe('model:a')
        ->and($graph->edge('edge:1')?->label)->toBe('related')
        ->and($graph->node('model:missing'))->toBeNull()
        ->and($graph->hasNode('model:b'))->toBeTrue();
});

it('throws a domain exception for missing nodes', function (): void {
    fakeGraph([fakeNode('model:a')])->nodeOrFail('model:missing');
})->throws(NodeNotFoundException::class);

it('ignores duplicate node identifiers after the first occurrence', function (): void {
    $graph = fakeGraph([
        fakeNode('model:a', label: 'first'),
        fakeNode('model:a', label: 'second'),
    ]);

    expect($graph->nodeCount())->toBe(1)
        ->and($graph->node('model:a')?->label)->toBe('first');
});

it('drops edges whose endpoints are missing', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:a')],
        [fakeEdge('model:a', 'model:missing')],
    );

    expect($graph->edgeCount())->toBe(0);
});

it('returns outgoing and incoming edges', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:a'), fakeNode('model:b'), fakeNode('model:c')],
        [
            fakeEdge('model:a', 'model:b', label: 'b'),
            fakeEdge('model:c', 'model:a', label: 'a'),
        ],
    );

    expect(array_map(fn ($edge) => $edge->label, $graph->outgoingEdges('model:a')))->toBe(['b'])
        ->and(array_map(fn ($edge) => $edge->label, $graph->incomingEdges('model:a')))->toBe(['a'])
        ->and(count($graph->neighbours('model:a')))->toBe(2);
});

it('filters nodes and edges by type', function (): void {
    $graph = fakeGraph(
        [
            fakeNode('panel:admin', NodeType::Panel),
            fakeNode('model:a'),
        ],
        [],
    );

    expect($graph->nodesOfType(NodeType::Panel))->toHaveCount(1)
        ->and($graph->nodesOfType(NodeType::Model))->toHaveCount(1)
        ->and($graph->edgesOfType(EdgeType::ModelRelation))->toHaveCount(0);
});

it('produces subgraphs preserving order and dropping dangling edges', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:a'), fakeNode('model:b'), fakeNode('model:c')],
        [
            fakeEdge('model:a', 'model:b'),
            fakeEdge('model:b', 'model:c'),
        ],
    );

    $subgraph = $graph->subgraph(['model:a', 'model:b']);

    expect($subgraph->nodeCount())->toBe(2)
        ->and($subgraph->edgeCount())->toBe(1)
        ->and($subgraph->edges[0]->target->value)->toBe('model:b');
});

it('serializes deterministically and round trips through arrays', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:a', metadata: ['table' => 'as'], badges: ['Resource'])],
        [],
    );

    $serialized = $graph->toArray();
    $restored = Graph::fromArray($serialized);

    expect($restored->toArray())->toBe($serialized)
        ->and($serialized['nodes'][0]['id'])->toBe('model:a');
});
