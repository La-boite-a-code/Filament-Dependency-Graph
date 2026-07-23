<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;

interface DependencyGraphManager
{
    public function discover(?DiscoveryContext $context = null): ApplicationSnapshot;

    public function graph(?GraphQuery $query = null): Graph;

    public function export(
        string $format,
        ?GraphQuery $query = null,
        ?ExportOptions $options = null,
    ): string;

    public function clearCache(): void;
}
