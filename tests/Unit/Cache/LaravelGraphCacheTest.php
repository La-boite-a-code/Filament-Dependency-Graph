<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Factory;
use LaBoiteACode\DependencyGraph\Cache\GraphCacheKey;
use LaBoiteACode\DependencyGraph\Cache\LaravelGraphCache;
use LaBoiteACode\DependencyGraph\Cache\NullGraphCache;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;

function emptySnapshot(): ApplicationSnapshot
{
    return new ApplicationSnapshot(
        fingerprint: 'abc',
        generatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        models: [],
        relations: [],
        resources: [],
        panels: [],
        warnings: [],
    );
}

function graphCache(): LaravelGraphCache
{
    config()->set('cache.stores.array_fdg', ['driver' => 'array']);

    return new LaravelGraphCache(app(Factory::class), 'array_fdg', 60);
}

it('stores and retrieves snapshots', function (): void {
    $cache = graphCache();
    $key = new GraphCacheKey('filament-dependency-graph:snapshot:test');

    expect($cache->has($key))->toBeFalse();

    $cache->put($key, emptySnapshot());

    expect($cache->has($key))->toBeTrue()
        ->and($cache->get($key)?->fingerprint)->toBe('abc');
});

it('treats corrupted payloads as misses and removes them', function (): void {
    $cache = graphCache();
    $key = new GraphCacheKey('filament-dependency-graph:snapshot:corrupted');

    app(Factory::class)->store('array_fdg')->forever($key->value, ['not' => 'a snapshot']);

    expect($cache->get($key))->toBeNull()
        ->and(app(Factory::class)->store('array_fdg')->get($key->value))->toBeNull();
});

it('flushes only its own keys', function (): void {
    $cache = graphCache();
    $store = app(Factory::class)->store('array_fdg');

    $key = new GraphCacheKey('filament-dependency-graph:snapshot:flushable');
    $cache->put($key, emptySnapshot());
    $store->forever('application-key', 'must-stay');

    $cache->flush();

    expect($cache->has($key))->toBeFalse()
        ->and($store->get('application-key'))->toBe('must-stay');
});

it('forgets single keys', function (): void {
    $cache = graphCache();
    $key = new GraphCacheKey('filament-dependency-graph:snapshot:forget');

    $cache->put($key, emptySnapshot());
    $cache->forget($key);

    expect($cache->has($key))->toBeFalse();
});

it('null cache never stores anything', function (): void {
    $cache = new NullGraphCache;
    $key = new GraphCacheKey('filament-dependency-graph:snapshot:null');

    $cache->put($key, emptySnapshot());

    expect($cache->has($key))->toBeFalse()
        ->and($cache->get($key))->toBeNull();
});
