<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface ModelDiscoverer
{
    /**
     * @return list<ModelData>
     */
    public function discover(DiscoveryContext $context): array;
}
