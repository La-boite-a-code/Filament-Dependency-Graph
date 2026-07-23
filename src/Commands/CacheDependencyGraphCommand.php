<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Commands;

use Illuminate\Console\Command;
use LaBoiteACode\DependencyGraph\Application\CacheDependencyGraph;
use LaBoiteACode\DependencyGraph\Application\DiscoverApplication;
use LaBoiteACode\DependencyGraph\Contracts\GraphCache;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use Throwable;

final class CacheDependencyGraphCommand extends Command
{
    protected $signature = 'filament-dependency-graph:cache
        {--scope= : Discovery scope, filament or laravel}
        {--panel=* : Only discover the given panel ids}
        {--no-schema : Skip database schema inspection}
        {--force : Rebuild even when a cached snapshot exists}';

    protected $description = 'Discover the application and cache the dependency graph snapshot';

    public function handle(
        CacheDependencyGraph $cacheDependencyGraph,
        DiscoverApplication $discoverApplication,
        GraphCache $cache,
    ): int {
        try {
            $context = $this->buildContext($discoverApplication);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('force') && $cache->has($discoverApplication->cacheKey($context))) {
            $this->components->info('A cached snapshot already exists for this configuration. Use --force to rebuild it.');

            return self::SUCCESS;
        }

        try {
            $snapshot = $cacheDependencyGraph->execute($context);
        } catch (Throwable $exception) {
            $this->components->error(sprintf('Dependency graph discovery failed: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $this->components->info('Dependency graph snapshot cached.');

        $this->components->twoColumnDetail('Models', (string) count($snapshot->models));
        $this->components->twoColumnDetail('Relations', (string) count($snapshot->relations));
        $this->components->twoColumnDetail('Resources', (string) count($snapshot->resources));
        $this->components->twoColumnDetail('Panels', (string) count($snapshot->panels));
        $this->components->twoColumnDetail('Warnings', (string) count($snapshot->warnings));

        foreach ($snapshot->warnings as $warning) {
            $this->components->warn(sprintf('[%s] %s', $warning->type, $warning->message));
        }

        return self::SUCCESS;
    }

    private function buildContext(DiscoverApplication $discoverApplication): DiscoveryContext
    {
        $context = $discoverApplication->defaultContext();

        $scope = $this->option('scope');

        if (is_string($scope) && $scope !== '') {
            $context = $context->withScope(GraphScope::from($scope));
        }

        /** @var list<string> $panels */
        $panels = array_values(array_filter((array) $this->option('panel'), 'is_string'));

        if ($panels !== []) {
            $context = $context->withPanelIds($panels);
        }

        if ($this->option('no-schema')) {
            $context = $context->withoutSchemaInspection();
        }

        return $context;
    }
}
