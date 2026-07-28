<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface LivewireComponentDiscoverer
{
    /**
     * @return list<LivewireComponentData>
     */
    public function discover(DiscoveryContext $context): array;
}
