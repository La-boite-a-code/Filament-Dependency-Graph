<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\GraphCache;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

/**
 * Rebuilds the snapshot and stores it in the cache unconditionally.
 */
final class CacheDependencyGraph
{
    public function __construct(
        private readonly ApplicationDiscovery $discovery,
        private readonly GraphCache $cache,
        private readonly DiscoverApplication $discoverApplication,
    ) {}

    public function execute(?DiscoveryContext $context = null): ApplicationSnapshot
    {
        $context ??= $this->discoverApplication->defaultContext();

        $snapshot = $this->discovery->discover($context);

        $this->cache->put($this->discoverApplication->cacheKey($context), $snapshot);

        return $snapshot;
    }
}
