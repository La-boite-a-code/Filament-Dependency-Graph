<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Contracts\ModelDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\AuditEntry;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\BrokenModel;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Image;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\ModelWithoutPrimaryKey;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

function discoveredModels(mixed ...$overrides): array
{
    $models = app(ModelDiscoverer::class)->discover(test()->fixtureContext(...$overrides));

    $byClass = [];

    foreach ($models as $model) {
        $byClass[$model->class] = $model;
    }

    return $byClass;
}

it('discovers models from the configured path', function (): void {
    $models = discoveredModels();

    expect($models)->toHaveKey(Order::class)
        ->and($models[Order::class]->shortName)->toBe('Order')
        ->and($models[Order::class]->table)->toBe('orders');
});

it('ignores abstract models and non model classes', function (): void {
    $classes = array_keys(discoveredModels());

    expect($classes)->not->toContain('LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Models\\AbstractContent')
        ->and($classes)->not->toContain('LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Models\\NotAModel');
});

it('discovers table, connection, primary key and key type metadata', function (): void {
    $models = discoveredModels();

    $audit = $models[AuditEntry::class];
    $image = $models[Image::class];

    expect($audit->table)->toBe('audit_entries')
        ->and($audit->connection)->toBe('audit')
        ->and($audit->primaryKey)->toBe('entry_id')
        ->and($audit->timestamps)->toBeFalse()
        ->and($image->keyType)->toBe('string')
        ->and($image->incrementing)->toBeFalse();
});

it('supports models that explicitly disable their primary key', function (): void {
    $model = discoveredModels()[ModelWithoutPrimaryKey::class];

    expect($model->primaryKey)->toBeNull()
        ->and($model->table)->toBe('reporting_view')
        ->and($model->status)->toBe(DiscoveryStatus::Complete);
});

it('discovers soft deletes, traits, casts and fillable attributes', function (): void {
    $order = discoveredModels()[Order::class];

    expect($order->softDeletes)->toBeTrue()
        ->and($order->traits)->toContain('Illuminate\\Database\\Eloquent\\SoftDeletes')
        ->and($order->casts)->toHaveKey('status')
        ->and($order->fillable)->toBe(['status']);
});

it('marks fixture models as application owned with the package base path', function (): void {
    $order = discoveredModels()[Order::class];

    expect($order->applicationOwned)->toBeTrue();
});

it('marks models outside the base path as not application owned', function (): void {
    $models = discoveredModels(basePath: '/nonexistent/base');

    expect($models[Order::class]->applicationOwned)->toBeFalse();
});

it('honors excluded classes', function (): void {
    $models = discoveredModels(excludedClasses: [Order::class]);

    expect($models)->not->toHaveKey(Order::class)
        ->and($models)->toHaveKey(AuditEntry::class);
});

it('honors excluded namespaces', function (): void {
    $models = discoveredModels(
        excludedNamespaces: ['LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Models\\'],
    );

    expect($models)->toBe([]);
});

it('honors excluded tables', function (): void {
    $models = discoveredModels(excludedTables: ['orders']);

    expect($models)->not->toHaveKey(Order::class);
});

it('handles model instantiation failure with a partial record', function (): void {
    $broken = discoveredModels()[BrokenModel::class];

    expect($broken->status)->toBe(DiscoveryStatus::Partial)
        ->and($broken->warnings)->not->toBeEmpty()
        ->and($broken->table)->toBe('broken_models');
});

it('produces stable identifiers and deduplicates classes', function (): void {
    $models = discoveredModels();

    $order = $models[Order::class];

    expect($order->id)
        ->toBe('model:la-boite-a-code.dependency-graph.tests.fixtures.models.order');

    $ids = array_map(static fn (ModelData $model): string => $model->id, array_values($models));

    expect($ids)->toBe(array_values(array_unique($ids)));
});
