<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\EdgeId;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\Graph\NodeId;
use LaBoiteACode\DependencyGraph\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Architecture');

/**
 * @param  array<string, scalar|array<array-key, mixed>|null>  $metadata
 * @param  list<string>  $badges
 */
function fakeNode(
    string $id,
    NodeType $type = NodeType::Model,
    ?string $label = null,
    array $metadata = [],
    array $badges = [],
    DiscoveryStatus $status = DiscoveryStatus::Complete,
): Node {
    return new Node(
        id: NodeId::fromString($id),
        type: $type,
        label: $label ?? $id,
        subtitle: null,
        metadata: $metadata,
        badges: $badges,
        status: $status,
    );
}

/**
 * @param  array<string, scalar|array<array-key, mixed>|null>  $metadata
 */
function fakeEdge(
    string $source,
    string $target,
    EdgeType $type = EdgeType::ModelRelation,
    string $label = 'related',
    array $metadata = [],
    ?string $id = null,
): Edge {
    return new Edge(
        id: EdgeId::fromString($id ?? "edge:{$type->value}:{$source}:{$target}:{$label}"),
        source: NodeId::fromString($source),
        target: NodeId::fromString($target),
        type: $type,
        label: $label,
        metadata: $metadata,
        status: DiscoveryStatus::Complete,
    );
}

/**
 * @param  list<Node>  $nodes
 * @param  list<Edge>  $edges
 */
function fakeGraph(array $nodes, array $edges = []): Graph
{
    return new Graph($nodes, $edges);
}
