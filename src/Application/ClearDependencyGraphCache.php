<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use LaBoiteACode\DependencyGraph\Contracts\GraphCache;

final class ClearDependencyGraphCache
{
    public function __construct(
        private readonly GraphCache $cache,
    ) {}

    public function execute(): void
    {
        $this->cache->flush();
    }
}
