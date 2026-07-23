<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface PanelDiscoverer
{
    /**
     * @return list<PanelData>
     */
    public function discover(DiscoveryContext $context): array;
}
