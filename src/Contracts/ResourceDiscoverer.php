<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface ResourceDiscoverer
{
    /**
     * @return list<ResourceData>
     */
    public function discover(DiscoveryContext $context): array;
}
