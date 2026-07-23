<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface ApplicationDiscovery
{
    /**
     * Coordinates every discovery service and produces one immutable
     * application snapshot. Partial failures are recorded as warnings
     * instead of aborting the whole discovery run.
     */
    public function discover(DiscoveryContext $context): ApplicationSnapshot;
}
