<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\ModelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\RelationDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\EloquentModelDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Comment;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Payment;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Report;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Tag;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;

/**
 * @return array<string, RelationData> Keyed by method name.
 */
function relationsOf(string $class, mixed ...$overrides): array
{
    $context = test()->fixtureContext(...$overrides);

    /** @var EloquentModelDiscoverer $discoverer */
    $discoverer = app(ModelDiscoverer::class);

    $model = $discoverer->discoverClass($class, $context);

    expect($model)->not->toBeNull();

    $relations = [];

    foreach (app(RelationDiscoverer::class)->discover($model, $context) as $relation) {
        $relations[$relation->method] = $relation;
    }

    return $relations;
}

it('discovers every supported relation type', function (): void {
    $order = relationsOf(Order::class);
    $customer = relationsOf(Customer::class);
    $user = relationsOf(User::class);
    $product = relationsOf(Product::class);
    $comment = relationsOf(Comment::class);
    $tag = relationsOf(Tag::class);

    expect($order['customer']->type)->toBe(RelationType::BelongsTo)
        ->and($order['invoice']->type)->toBe(RelationType::HasOne)
        ->and($order['items']->type)->toBe(RelationType::HasMany)
        ->and($product['tags']->type)->toBe(RelationType::BelongsToMany)
        ->and($order['payment']->type)->toBe(RelationType::HasOneThrough)
        ->and($customer['orderItems']->type)->toBe(RelationType::HasManyThrough)
        ->and($comment['commentable']->type)->toBe(RelationType::MorphTo)
        ->and($user['avatar']->type)->toBe(RelationType::MorphOne)
        ->and($order['comments']->type)->toBe(RelationType::MorphMany)
        ->and($order['tags']->type)->toBe(RelationType::MorphToMany)
        ->and($tag['orders']->type)->toBe(RelationType::MorphedByMany);
});

it('stores relation method, keys and pivot metadata', function (): void {
    $order = relationsOf(Order::class);
    $product = relationsOf(Product::class);

    expect($order['customer']->method)->toBe('customer')
        ->and($order['customer']->foreignKey)->toBe('customer_id')
        ->and($order['customer']->ownerKey)->toBe('id')
        ->and($order['items']->foreignKey)->toBe('order_id')
        ->and($order['items']->localKey)->toBe('id')
        ->and($product['tags']->pivotTable)->toBe('product_tag')
        ->and($order['comments']->morphType)->toBe('commentable_type');
});

it('resolves relation targets to stable model identifiers', function (): void {
    $order = relationsOf(Order::class);

    expect($order['customer']->relatedClass)->toBe(Customer::class)
        ->and($order['customer']->targetModelId)
        ->toBe('model:la-boite-a-code.dependency-graph.tests.fixtures.models.customer');
});

it('keeps two relations pointing at the same target separate', function (): void {
    $order = relationsOf(Order::class);

    expect($order)->toHaveKeys(['billingAddress', 'shippingAddress'])
        ->and($order['billingAddress']->foreignKey)->toBe('billing_address_id')
        ->and($order['shippingAddress']->foreignKey)->toBe('shipping_address_id')
        ->and($order['billingAddress']->id)->not->toBe($order['shippingAddress']->id);
});

it('handles self referencing relations', function (): void {
    $comment = relationsOf(Comment::class);

    expect($comment['parent']->type)->toBe(RelationType::BelongsTo)
        ->and($comment['parent']->targetModelId)->toBe($comment['parent']->sourceModelId);
});

it('verifies nullability from schema metadata for belongsTo keys', function (): void {
    $order = relationsOf(Order::class);
    $comment = relationsOf(Comment::class);

    expect($order['customer']->nullable)->toBeFalse()
        ->and($order['billingAddress']->nullable)->toBeTrue()
        ->and($comment['parent']->nullable)->toBeTrue();
});

it('leaves nullability unknown when schema inspection is disabled', function (): void {
    $order = relationsOf(Order::class, inspectDatabaseSchema: false);

    expect($order['customer']->nullable)->toBeNull();
});

it('marks morphTo relations as unresolved polymorphic targets', function (): void {
    $comment = relationsOf(Comment::class);

    expect($comment['commentable']->targetModelId)->toBeNull()
        ->and($comment['commentable']->polymorphic)->toBeTrue()
        ->and($comment['commentable']->morphType)->toBe('commentable_type')
        ->and($comment['commentable']->warnings)->not->toBeEmpty();
});

it('ignores scopes, accessors, static methods and methods with parameters', function (): void {
    $order = relationsOf(Order::class);

    expect($order)->not->toHaveKey('scopeRecent')
        ->and($order)->not->toHaveKey('getReferenceAttribute')
        ->and($order)->not->toHaveKey('reorderItems');
});

it('uses docblock return types when enabled', function (): void {
    $invoice = relationsOf(LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Invoice::class);

    expect($invoice)->toHaveKey('payments')
        ->and($invoice['payments']->type)->toBe(RelationType::HasMany)
        ->and($invoice['payments']->relatedClass)->toBe(Payment::class);
});

it('skips docblock relations when docblocks are disabled', function (): void {
    $invoice = relationsOf(
        LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Invoice::class,
        useDocblocks: false,
    );

    expect($invoice)->not->toHaveKey('payments');
});

it('does not invoke untyped methods unless heuristics are enabled', function (): void {
    $withoutHeuristics = relationsOf(Customer::class);
    $withHeuristics = relationsOf(Customer::class, useHeuristicInvocation: true);

    expect($withoutHeuristics)->not->toHaveKey('untypedOrders')
        ->and($withHeuristics)->toHaveKey('untypedOrders')
        ->and($withHeuristics['untypedOrders']->type)->toBe(RelationType::HasMany);
});

it('honors excluded relations', function (): void {
    $order = relationsOf(Order::class, excludedRelations: [Order::class . '::tags']);

    expect($order)->not->toHaveKey('tags')
        ->and($order)->toHaveKey('customer');
});

it('survives throwing relation methods with a partial record', function (): void {
    $report = relationsOf(Report::class);

    expect($report['author']->status)->toBe(DiscoveryStatus::Partial)
        ->and($report['author']->type)->toBe(RelationType::BelongsTo)
        ->and($report['author']->warnings)->not->toBeEmpty();
});

it('survives relations pointing at missing classes', function (): void {
    $report = relationsOf(Report::class);

    expect($report['missingTarget']->status)->toBe(DiscoveryStatus::Partial);
});

it('detects inverse relations in the full snapshot', function (): void {
    $snapshot = app(ApplicationDiscovery::class)->discover(test()->fixtureContext());

    $byId = [];

    foreach ($snapshot->relations as $relation) {
        $byId[$relation->id] = $relation;
    }

    $orderCustomer = $byId['relation:la-boite-a-code.dependency-graph.tests.fixtures.models.order:customer'];
    $reportMissing = $byId['relation:la-boite-a-code.dependency-graph.tests.fixtures.models.report:missingTarget'];

    expect($orderCustomer->inverseDiscovered)->toBeTrue()
        ->and($reportMissing->inverseDiscovered)->toBeFalse();
});
