<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Cache\GraphCacheKey;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

function cacheKey(DiscoveryContext $context, string $schema = '1.0'): GraphCacheKey
{
    return GraphCacheKey::create(
        context: $context,
        applicationEnvironment: 'testing',
        laravelVersion: '13.0.0',
        filamentVersion: '5.0.0',
        phpVersion: '8.4.0',
        schemaVersion: $schema,
    );
}

it('is stable for identical inputs', function (): void {
    expect(cacheKey(new DiscoveryContext)->value)->toBe(cacheKey(new DiscoveryContext)->value);
});

it('changes when the configuration changes', function (): void {
    $default = cacheKey(new DiscoveryContext);
    $changedScope = cacheKey(new DiscoveryContext(scope: GraphScope::Laravel));
    $changedPaths = cacheKey(new DiscoveryContext(modelPaths: ['/app/Domain']));

    expect($changedScope->value)->not->toBe($default->value)
        ->and($changedPaths->value)->not->toBe($default->value);
});

it('changes with the schema version', function (): void {
    expect(cacheKey(new DiscoveryContext, '1.0')->value)
        ->not->toBe(cacheKey(new DiscoveryContext, '2.0')->value);
});

it('uses the package cache prefix', function (): void {
    expect(cacheKey(new DiscoveryContext)->value)->toStartWith('filament-dependency-graph:snapshot:');
});
