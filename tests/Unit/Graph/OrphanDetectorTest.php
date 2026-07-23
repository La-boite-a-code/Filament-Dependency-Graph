<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Graph\OrphanDetector;

it('reports models with no relations and no resource as orphans', function (): void {
    $graph = fakeGraph(
        [
            fakeNode('model:connected'),
            fakeNode('model:target'),
            fakeNode('model:orphan'),
            fakeNode('model:exposed'),
            fakeNode('resource:exposed', NodeType::Resource),
        ],
        [
            fakeEdge('model:connected', 'model:target'),
            fakeEdge('resource:exposed', 'model:exposed', EdgeType::ResourceUsesModel),
        ],
    );

    expect((new OrphanDetector)->detect($graph))->toBe(['model:orphan']);
});

it('never reports non model nodes', function (): void {
    $graph = fakeGraph([
        fakeNode('panel:isolated', NodeType::Panel),
        fakeNode('resource:isolated', NodeType::Resource),
    ]);

    expect((new OrphanDetector)->detect($graph))->toBe([]);
});
