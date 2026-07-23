<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\PackagePath;

it('extracts short names and namespaces from class names', function (): void {
    expect(ClassName::shortName('App\\Models\\Order'))->toBe('Order')
        ->and(ClassName::shortName('Order'))->toBe('Order')
        ->and(ClassName::namespace('App\\Models\\Order'))->toBe('App\\Models')
        ->and(ClassName::namespace('Order'))->toBe('')
        ->and(ClassName::normalize('\\App\\Models\\Order'))->toBe('App\\Models\\Order');
});

it('compares class names case insensitively', function (): void {
    expect(ClassName::equals('App\\Models\\Order', '\\app\\models\\order'))->toBeTrue()
        ->and(ClassName::equals('App\\Models\\Order', 'App\\Models\\Customer'))->toBeFalse();
});

it('matches namespace prefixes', function (): void {
    expect(NamespaceMatcher::matchesNamespace('App\\Models\\Order', ['App\\Models\\']))->toBeTrue()
        ->and(NamespaceMatcher::matchesNamespace('App\\Models\\Order', ['App\\Models']))->toBeTrue()
        ->and(NamespaceMatcher::matchesNamespace('App\\Services\\Order', ['App\\Models\\']))->toBeFalse()
        ->and(NamespaceMatcher::matchesNamespace('App\\Models\\Order', []))->toBeFalse();
});

it('matches exact class names', function (): void {
    expect(NamespaceMatcher::matchesClass('App\\Models\\Order', ['App\\Models\\Order']))->toBeTrue()
        ->and(NamespaceMatcher::matchesClass('App\\Models\\Order', ['App\\Models\\OrderItem']))->toBeFalse();
});

it('detects paths inside roots', function (): void {
    expect(PackagePath::isInside('/app/src/Models/Order.php', '/app'))->toBeTrue()
        ->and(PackagePath::isInside('/other/Order.php', '/app'))->toBeFalse()
        ->and(PackagePath::isInside('/app-other/Order.php', '/app'))->toBeFalse();
});

it('classifies application ownership', function (): void {
    expect(PackagePath::isApplicationOwned('/app/app/Models/Order.php', '/app', '/app/vendor'))->toBeTrue()
        ->and(PackagePath::isApplicationOwned('/app/vendor/pkg/src/Model.php', '/app', '/app/vendor'))->toBeFalse()
        ->and(PackagePath::isApplicationOwned('/elsewhere/Order.php', '/app', '/app/vendor'))->toBeFalse();
});
