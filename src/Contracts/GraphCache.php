<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use DateInterval;
use LaBoiteACode\DependencyGraph\Cache\GraphCacheKey;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;

interface GraphCache
{
    public function has(GraphCacheKey $key): bool;

    public function get(GraphCacheKey $key): ?ApplicationSnapshot;

    public function put(
        GraphCacheKey $key,
        ApplicationSnapshot $snapshot,
        ?DateInterval $ttl = null,
    ): void;

    public function forget(GraphCacheKey $key): void;

    public function flush(): void;
}
