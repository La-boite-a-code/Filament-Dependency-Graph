<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Graph\CycleDetector;
use LaBoiteACode\DependencyGraph\Graph\DefaultGraphBuilder;
use LaBoiteACode\DependencyGraph\Graph\EdgeFactory;
use LaBoiteACode\DependencyGraph\Graph\NodeFactory;
use LaBoiteACode\DependencyGraph\Graph\OrphanDetector;

function builder(): DefaultGraphBuilder
{
    return new DefaultGraphBuilder(
        new NodeFactory,
        new EdgeFactory,
        new CycleDetector,
        new OrphanDetector,
    );
}

function fixtureModelData(string $class, bool $softDeletes = false): ModelData
{
    $short = substr($class, (int) strrpos($class, '\\') + 1);

    return new ModelData(
        id: LaBoiteACode\DependencyGraph\Support\StableIdentifier::model($class),
        class: $class,
        shortName: $short,
        namespace: 'App\\Models',
        table: strtolower($short) . 's',
        connection: 'default',
        primaryKey: 'id',
        keyType: 'int',
        incrementing: true,
        timestamps: true,
        softDeletes: $softDeletes,
        traits: [],
        casts: [],
        fillable: [],
        guarded: ['*'],
        hidden: [],
        visible: [],
        status: DiscoveryStatus::Complete,
        warnings: [],
        applicationOwned: true,
    );
}

function fixtureRelationData(
    string $sourceClass,
    string $method,
    RelationType $type,
    ?string $targetClass,
): RelationData {
    return new RelationData(
        id: LaBoiteACode\DependencyGraph\Support\StableIdentifier::relation($sourceClass, $method),
        sourceModelId: LaBoiteACode\DependencyGraph\Support\StableIdentifier::model($sourceClass),
        targetModelId: $targetClass === null
            ? null
            : LaBoiteACode\DependencyGraph\Support\StableIdentifier::model($targetClass),
        method: $method,
        type: $type,
        relatedClass: $targetClass,
        foreignKey: null,
        ownerKey: null,
        localKey: null,
        pivotTable: null,
        morphType: $type === RelationType::MorphTo ? $method . '_type' : null,
        nullable: null,
        polymorphic: $type->isPolymorphic(),
        inverseDiscovered: false,
        status: DiscoveryStatus::Complete,
        warnings: [],
    );
}

function fixtureSnapshot(): ApplicationSnapshot
{
    $orderResource = new ResourceData(
        id: 'resource:app.resources.order-resource',
        class: 'App\\Resources\\OrderResource',
        shortName: 'OrderResource',
        modelClass: 'App\\Models\\Order',
        modelId: 'model:app.models.order',
        label: 'Order',
        pluralLabel: 'Orders',
        navigationGroup: 'Shop',
        navigationIcon: null,
        panelIds: ['admin'],
        pages: [],
        relationManagers: [],
        status: DiscoveryStatus::Complete,
        warnings: [],
    );

    return new ApplicationSnapshot(
        fingerprint: 'test',
        generatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        models: [
            fixtureModelData('App\\Models\\Address'),
            fixtureModelData('App\\Models\\Comment'),
            fixtureModelData('App\\Models\\Lonely'),
            fixtureModelData('App\\Models\\Order', softDeletes: true),
            fixtureModelData('App\\Models\\Team'),
            fixtureModelData('App\\Models\\User'),
        ],
        relations: [
            fixtureRelationData('App\\Models\\Comment', 'commentable', RelationType::MorphTo, null),
            fixtureRelationData('App\\Models\\Order', 'billingAddress', RelationType::BelongsTo, 'App\\Models\\Address'),
            fixtureRelationData('App\\Models\\Order', 'shippingAddress', RelationType::BelongsTo, 'App\\Models\\Address'),
            fixtureRelationData('App\\Models\\Team', 'owner', RelationType::BelongsTo, 'App\\Models\\User'),
            fixtureRelationData('App\\Models\\User', 'team', RelationType::BelongsTo, 'App\\Models\\Team'),
        ],
        resources: [$orderResource],
        panels: [
            new PanelData(id: 'admin', path: 'admin', domain: null, resourceIds: ['resource:app.resources.order-resource']),
        ],
        warnings: [],
    );
}

it('creates panel, resource and model nodes with structural edges', function (): void {
    $graph = builder()->build(fixtureSnapshot());

    expect($graph->hasNode('panel:admin'))->toBeTrue()
        ->and($graph->hasNode('resource:app.resources.order-resource'))->toBeTrue()
        ->and($graph->hasNode('model:app.models.order'))->toBeTrue()
        ->and($graph->edgesOfType(EdgeType::PanelRegistersResource))->toHaveCount(1)
        ->and($graph->edgesOfType(EdgeType::ResourceUsesModel))->toHaveCount(1);
});

it('preserves multiple relation edges between the same models', function (): void {
    $graph = builder()->build(fixtureSnapshot());

    $edges = array_filter(
        $graph->edgesOfType(EdgeType::ModelRelation),
        static fn ($edge): bool => $edge->target->value === 'model:app.models.address',
    );

    expect($edges)->toHaveCount(2);
});

it('creates one polymorphic placeholder node for morphTo relations', function (): void {
    $graph = builder()->build(fixtureSnapshot());

    expect($graph->hasNode('polymorphic:app.models.comment:commentable'))->toBeTrue()
        ->and($graph->nodesOfType(NodeType::PolymorphicTarget))->toHaveCount(1);
});

it('derives model badges including orphan and cycle markers', function (): void {
    $graph = builder()->build(fixtureSnapshot());

    expect($graph->node('model:app.models.order')?->badges)
        ->toBe(['Resource', 'SoftDeletes'])
        ->and($graph->node('model:app.models.lonely')?->badges)
        ->toContain('Orphan')
        ->and($graph->node('model:app.models.lonely')?->badges)
        ->toContain('No Resource')
        ->and($graph->node('model:app.models.user')?->badges)
        ->toContain('Cycle')
        ->and($graph->node('model:app.models.team')?->badges)
        ->toContain('Cycle');
});

it('prevents duplicate nodes', function (): void {
    $snapshot = fixtureSnapshot();

    $graph = builder()->build(new ApplicationSnapshot(
        fingerprint: $snapshot->fingerprint,
        generatedAt: $snapshot->generatedAt,
        models: [...$snapshot->models, fixtureModelData('App\\Models\\Order')],
        relations: $snapshot->relations,
        resources: $snapshot->resources,
        panels: $snapshot->panels,
        warnings: [],
    ));

    $orderNodes = array_filter(
        $graph->nodes,
        static fn ($node): bool => $node->id->value === 'model:app.models.order',
    );

    expect($orderNodes)->toHaveCount(1);
});

it('sorts nodes by type priority then label and edges deterministically', function (): void {
    $graph = builder()->build(fixtureSnapshot());

    $types = array_map(static fn ($node): int => $node->type->sortPriority(), $graph->nodes);
    $sortedTypes = $types;
    sort($sortedTypes);

    expect($types)->toBe($sortedTypes);

    $edgeKeys = array_map(
        static fn ($edge): array => [$edge->source->value, $edge->target->value, $edge->type->value, $edge->label],
        $graph->edges,
    );
    $sortedEdgeKeys = $edgeKeys;
    sort($sortedEdgeKeys);

    expect($edgeKeys)->toBe($sortedEdgeKeys);
});

it('produces identical graphs for identical snapshots', function (): void {
    expect(builder()->build(fixtureSnapshot())->toArray())
        ->toBe(builder()->build(fixtureSnapshot())->toArray());
});
