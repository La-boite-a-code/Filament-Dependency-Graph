<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentVersion;
use LaBoiteACode\DependencyGraph\Contracts\DependencyGraphManager;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;

final class DefaultDependencyGraphManager implements DependencyGraphManager
{
    public function __construct(
        private readonly DiscoverApplication $discoverApplication,
        private readonly BuildDependencyGraph $buildGraph,
        private readonly ExportDependencyGraph $exportGraph,
        private readonly ClearDependencyGraphCache $clearGraphCache,
        private readonly Repository $config,
        private readonly Application $app,
    ) {}

    public function discover(?DiscoveryContext $context = null): ApplicationSnapshot
    {
        return $this->discoverApplication->execute($context);
    }

    public function graph(?GraphQuery $query = null): Graph
    {
        $snapshot = $this->discoverApplication->execute();

        return $this->buildGraph->execute($snapshot, $query ?? $this->defaultQuery());
    }

    public function export(
        string $format,
        ?GraphQuery $query = null,
        ?ExportOptions $options = null,
    ): string {
        $query ??= $this->defaultQuery();

        $snapshot = $this->discoverApplication->execute();
        $graph = $this->buildGraph->execute($snapshot, $query);

        return $this->exportGraph->execute(
            $graph,
            $format,
            $this->enrichOptions($options ?? new ExportOptions, $snapshot, $query),
        );
    }

    public function clearCache(): void
    {
        $this->clearGraphCache->execute();
    }

    public function defaultQuery(): GraphQuery
    {
        $scope = $this->config->get('filament-dependency-graph.default_scope', GraphScope::Filament);

        if (is_string($scope)) {
            $scope = GraphScope::tryFrom($scope) ?? GraphScope::Filament;
        }

        if (! $scope instanceof GraphScope) {
            $scope = GraphScope::Filament;
        }

        if (
            $scope === GraphScope::Laravel
            && ! $this->config->get('filament-dependency-graph.laravel_scope_enabled', true)
        ) {
            $scope = GraphScope::Filament;
        }

        $direction = TraversalDirection::tryFrom(
            (string) $this->config->get('filament-dependency-graph.graph.default_direction', 'both'),
        ) ?? TraversalDirection::Both;

        return new GraphQuery(
            scope: $scope,
            direction: $direction,
            includeOrphans: (bool) $this->config->get('filament-dependency-graph.graph.show_orphans', true),
        );
    }

    private function enrichOptions(
        ExportOptions $options,
        ApplicationSnapshot $snapshot,
        GraphQuery $query,
    ): ExportOptions {
        return new ExportOptions(
            prettyPrint: $options->prettyPrint,
            includeEdgeLabels: $options->includeEdgeLabels,
            mermaidDirection: $options->mermaidDirection,
            mermaidNodeWarningThreshold: $options->mermaidNodeWarningThreshold,
            scope: $options->scope ?? $query->scope->value,
            filters: $options->filters === [] ? $query->toArray() : $options->filters,
            generatedAt: $options->generatedAt ?? $snapshot->generatedAt,
            environment: $options->environment === [] ? [
                'laravel' => $this->app->version(),
                'filament' => FilamentVersion::full() ?? 'unknown',
                'php' => PHP_VERSION,
            ] : $options->environment,
            warnings: $options->warnings === [] ? $snapshot->warnings : $options->warnings,
        );
    }
}
