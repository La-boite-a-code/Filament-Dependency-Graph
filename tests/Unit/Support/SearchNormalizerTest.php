<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Support\SearchNormalizer;

it('lowercases input', function (): void {
    expect(SearchNormalizer::normalize('ORDER'))->toBe('order');
});

it('is accent insensitive', function (): void {
    expect(SearchNormalizer::normalize('Commandé'))->toBe('commande')
        ->and(SearchNormalizer::normalize('Modèle'))->toBe('modele');
});

it('treats namespace separators as spaces', function (): void {
    expect(SearchNormalizer::normalize('App\\Models\\Order'))->toBe('app models order');
});

it('splits camel case', function (): void {
    expect(SearchNormalizer::normalize('OrderItem'))->toBe('order item')
        ->and(SearchNormalizer::normalize('billingAddress'))->toBe('billing address');
});

it('treats hyphens and underscores as spaces', function (): void {
    expect(SearchNormalizer::normalize('order_items'))->toBe('order items')
        ->and(SearchNormalizer::normalize('order-resource'))->toBe('order resource');
});

it('collapses whitespace and trims', function (): void {
    expect(SearchNormalizer::normalize('  order   item  '))->toBe('order item');
});

it('splits values into tokens', function (): void {
    expect(SearchNormalizer::tokens('App\\Models\\OrderItem'))
        ->toBe(['app', 'models', 'order', 'item'])
        ->and(SearchNormalizer::tokens('   '))->toBe([]);
});
