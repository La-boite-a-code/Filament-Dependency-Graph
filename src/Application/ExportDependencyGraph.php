<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;
use LaBoiteACode\DependencyGraph\Export\ExportManager;

final class ExportDependencyGraph
{
    public function __construct(
        private readonly ExportManager $exports,
    ) {}

    public function execute(Graph $graph, string $format, ?ExportOptions $options = null): string
    {
        return $this->exports->export($format, $graph, $options ?? new ExportOptions);
    }
}
