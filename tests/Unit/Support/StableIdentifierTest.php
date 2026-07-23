<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

it('normalizes fully qualified class names to lowercase dotted paths', function (): void {
    expect(StableIdentifier::normalizeClass('App\\Models\\Order'))->toBe('app.models.order')
        ->and(StableIdentifier::normalizeClass('\\App\\Models\\Order'))->toBe('app.models.order')
        ->and(StableIdentifier::normalizeClass('App\\Filament\\Resources\\OrderResource'))
        ->toBe('app.filament.resources.order-resource')
        ->and(StableIdentifier::normalizeClass('App\\Models\\OrderItem'))->toBe('app.models.order-item');
});

it('builds identifiers matching the documented format', function (): void {
    expect(StableIdentifier::panel('Admin'))->toBe('panel:admin')
        ->and(StableIdentifier::model('App\\Models\\Order'))->toBe('model:app.models.order')
        ->and(StableIdentifier::resource('App\\Filament\\Resources\\OrderResource'))
        ->toBe('resource:app.filament.resources.order-resource')
        ->and(StableIdentifier::relation('App\\Models\\Order', 'customer'))
        ->toBe('relation:app.models.order:customer');
});

it('keeps the relation method case exactly', function (): void {
    expect(StableIdentifier::relation('App\\Models\\Order', 'billingAddress'))
        ->toBe('relation:app.models.order:billingAddress');
});

it('collapses repeated separators', function (): void {
    expect(StableIdentifier::normalizeClass('App\\\\Models\\\\Order'))->toBe('app.models.order')
        ->and(StableIdentifier::panel('my  panel'))->toBe('panel:my-panel');
});

it('produces identical identifiers across calls', function (): void {
    expect(StableIdentifier::model('App\\Models\\Order'))
        ->toBe(StableIdentifier::model('App\\Models\\Order'));
});

it('never contains uppercase characters or random parts', function (): void {
    $identifier = StableIdentifier::resource('App\\Filament\\Resources\\OrderItemResource');

    expect($identifier)->toBe(strtolower($identifier));
});

it('builds edge identifiers from type, endpoints and discriminator', function (): void {
    $identifier = StableIdentifier::edge(
        EdgeType::ModelRelation,
        'model:app.models.order',
        'model:app.models.customer',
        'customer',
    );

    expect($identifier)->toBe('edge:model_relation:model:app.models.order:model:app.models.customer:customer');
});
