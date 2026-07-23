<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\ValueObjects;

use DateTimeImmutable;

final readonly class ExportOptions
{
    /**
     * @param  array<string, mixed>  $filters  Filters applied to the exported graph, for traceability.
     * @param  array<string, string>  $environment  Runtime versions included in machine-readable exports.
     * @param  list<DiscoveryWarning>  $warnings
     */
    public function __construct(
        public bool $prettyPrint = true,
        public bool $includeEdgeLabels = true,
        public string $mermaidDirection = 'LR',
        public int $mermaidNodeWarningThreshold = 150,
        public ?string $scope = null,
        public array $filters = [],
        public ?DateTimeImmutable $generatedAt = null,
        public array $environment = [],
        public array $warnings = [],
    ) {}
}
