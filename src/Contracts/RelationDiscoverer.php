<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface RelationDiscoverer
{
    /**
     * @return list<RelationData>
     */
    public function discover(ModelData $model, DiscoveryContext $context): array;
}
