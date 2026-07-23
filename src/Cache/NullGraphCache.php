<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Cache;

use DateInterval;
use LaBoiteACode\DependencyGraph\Contracts\GraphCache;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;

/**
 * No-op cache, bound when caching is disabled.
 */
final class NullGraphCache implements GraphCache
{
    public function has(GraphCacheKey $key): bool
    {
        return false;
    }

    public function get(GraphCacheKey $key): ?ApplicationSnapshot
    {
        return null;
    }

    public function put(GraphCacheKey $key, ApplicationSnapshot $snapshot, ?DateInterval $ttl = null): void {}

    public function forget(GraphCacheKey $key): void {}

    public function flush(): void {}
}
