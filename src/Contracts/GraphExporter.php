<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;

interface GraphExporter
{
    /**
     * Unique format identifier, for example "json" or "mermaid".
     */
    public function format(): string;

    public function export(Graph $graph, ExportOptions $options): string;
}
