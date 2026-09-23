<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\PolicyData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface PolicyDiscoverer
{
    /**
     * Resolves the policy guarding each model without instantiating it.
     *
     * @param  list<string>  $modelClasses
     * @return list<PolicyData>
     */
    public function discover(array $modelClasses, DiscoveryContext $context): array;
}
