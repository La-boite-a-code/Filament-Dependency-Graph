<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;

/**
 * Discoverers implementing this interface accumulate run-level warnings that
 * do not belong to a single produced DTO. The orchestrator drains them after
 * each discovery call.
 */
interface CollectsDiscoveryWarnings
{
    /**
     * Returns collected warnings and clears the internal buffer.
     *
     * @return list<DiscoveryWarning>
     */
    public function pullWarnings(): array;
}
