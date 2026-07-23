<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use LaBoiteACode\DependencyGraph\Cache\GraphCacheKey;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentVersion;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\GraphCache;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

/**
 * Discovers the application, reading through the snapshot cache when one is
 * available for the given context.
 */
final class DiscoverApplication
{
    public function __construct(
        private readonly ApplicationDiscovery $discovery,
        private readonly GraphCache $cache,
        private readonly Repository $config,
        private readonly Application $app,
    ) {}

    public function execute(?DiscoveryContext $context = null, bool $useCache = true): ApplicationSnapshot
    {
        $context ??= $this->defaultContext();
        $key = $this->cacheKey($context);

        if ($useCache) {
            $cached = $this->cache->get($key);

            if ($cached instanceof ApplicationSnapshot) {
                return $cached;
            }
        }

        $snapshot = $this->discovery->discover($context);

        $this->cache->put($key, $snapshot);

        return $snapshot;
    }

    /**
     * Builds the discovery context from the package configuration.
     */
    public function defaultContext(): DiscoveryContext
    {
        $scope = $this->config->get('filament-dependency-graph.default_scope', GraphScope::Filament);

        if (is_string($scope)) {
            $scope = GraphScope::from($scope);
        }

        if (! $scope instanceof GraphScope) {
            $scope = GraphScope::Filament;
        }

        return new DiscoveryContext(
            scope: $scope,
            modelPaths: $this->stringList('model_paths', [$this->app->basePath('app/Models')]),
            modelNamespaces: $this->stringList('model_namespaces', ['App\\Models\\']),
            excludedClasses: $this->stringList('exclude.classes'),
            excludedNamespaces: $this->stringList('exclude.namespaces'),
            excludedTables: $this->stringList('exclude.tables'),
            excludedRelations: $this->stringList('exclude.relations'),
            vendorModelsEnabled: (bool) $this->config->get('filament-dependency-graph.vendor_models.enabled', false),
            vendorModelNamespaces: $this->stringList('vendor_models.namespaces'),
            discoverRelations: (bool) $this->config->get('filament-dependency-graph.discovery.relations', true),
            inspectDatabaseSchema: (bool) $this->config->get('filament-dependency-graph.discovery.database_schema', true),
            useDocblocks: (bool) $this->config->get('filament-dependency-graph.discovery.docblocks', true),
            useHeuristicInvocation: (bool) $this->config->get(
                'filament-dependency-graph.discovery.heuristic_relation_invocation',
                false,
            ),
            panelIds: [],
            basePath: $this->app->basePath(),
            vendorPath: $this->app->basePath('vendor'),
        );
    }

    public function cacheKey(DiscoveryContext $context): GraphCacheKey
    {
        return GraphCacheKey::create(
            context: $context,
            applicationEnvironment: (string) $this->app->environment(),
            laravelVersion: $this->app->version(),
            filamentVersion: FilamentVersion::full() ?? 'unknown',
            phpVersion: PHP_VERSION,
        );
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringList(string $key, array $default = []): array
    {
        $values = $this->config->get('filament-dependency-graph.' . $key, $default);

        if (! is_array($values)) {
            return $default;
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => (string) $value, $values),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
