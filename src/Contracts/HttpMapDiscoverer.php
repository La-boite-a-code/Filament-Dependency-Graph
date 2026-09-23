<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface HttpMapDiscoverer
{
    /**
     * Maps the application routes and everything they lead to, policies
     * excepted: those depend on the final model set.
     */
    public function discover(DiscoveryContext $context): HttpMapData;
}
