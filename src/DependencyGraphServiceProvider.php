<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use LaBoiteACode\DependencyGraph\Application\DefaultDependencyGraphManager;
use LaBoiteACode\DependencyGraph\Cache\LaravelGraphCache;
use LaBoiteACode\DependencyGraph\Cache\NullGraphCache;
use LaBoiteACode\DependencyGraph\Commands\CacheDependencyGraphCommand;
use LaBoiteACode\DependencyGraph\Commands\ClearDependencyGraphCacheCommand;
use LaBoiteACode\DependencyGraph\Commands\ExportDependencyGraphCommand;
use LaBoiteACode\DependencyGraph\Compatibility\Filament4Adapter;
use LaBoiteACode\DependencyGraph\Compatibility\Filament5Adapter;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentVersion;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\DependencyGraphManager;
use LaBoiteACode\DependencyGraph\Contracts\GraphBuilder;
use LaBoiteACode\DependencyGraph\Contracts\GraphCache;
use LaBoiteACode\DependencyGraph\Contracts\LivewireComponentDiscoverer as LivewireComponentDiscovererContract;
use LaBoiteACode\DependencyGraph\Contracts\ModelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Contracts\PanelDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\RelationDiscoverer;
use LaBoiteACode\DependencyGraph\Contracts\ResourceDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\ClassCandidateFinder;
use LaBoiteACode\DependencyGraph\Discovery\EloquentModelDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\EloquentRelationDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\FilamentPanelDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\FilamentResourceDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\LaravelApplicationDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\LivewireComponentDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\ModelInstantiator;
use LaBoiteACode\DependencyGraph\Discovery\Support\SchemaInspector;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\InvalidConfigurationException;
use LaBoiteACode\DependencyGraph\Export\ExportManager;
use LaBoiteACode\DependencyGraph\Export\JsonGraphExporter;
use LaBoiteACode\DependencyGraph\Export\MermaidGraphExporter;
use LaBoiteACode\DependencyGraph\Graph\DefaultGraphBuilder;
use LaBoiteACode\DependencyGraph\Inspection\DefaultNodeInspector;
use LaBoiteACode\DependencyGraph\Inspection\LivewireComponentInspector;
use LaBoiteACode\DependencyGraph\Inspection\ModelInspector;
use LaBoiteACode\DependencyGraph\Inspection\PanelInspector;
use LaBoiteACode\DependencyGraph\Inspection\ResourceInspector;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DependencyGraphServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-dependency-graph';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasCommands([
                CacheDependencyGraphCommand::class,
                ClearDependencyGraphCacheCommand::class,
                ExportDependencyGraphCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ModelInstantiator::class);
        $this->app->singleton(ClassCandidateFinder::class);
        $this->app->singleton(SchemaInspector::class);

        $this->app->singleton(FilamentAdapter::class, static function (): FilamentAdapter {
            return match (FilamentVersion::detect()) {
                4 => new Filament4Adapter,
                5 => new Filament5Adapter,
                default => throw InvalidConfigurationException::because(
                    'no compatible Filament version is installed, Filament 4.x or 5.x is required.',
                ),
            };
        });

        $this->app->singleton(ModelDiscoverer::class, EloquentModelDiscoverer::class);
        $this->app->singleton(RelationDiscoverer::class, EloquentRelationDiscoverer::class);
        $this->app->singleton(PanelDiscoverer::class, FilamentPanelDiscoverer::class);
        $this->app->singleton(ResourceDiscoverer::class, FilamentResourceDiscoverer::class);
        $this->app->singleton(LivewireComponentDiscovererContract::class, LivewireComponentDiscoverer::class);
        $this->app->singleton(ApplicationDiscovery::class, LaravelApplicationDiscoverer::class);
        $this->app->singleton(GraphBuilder::class, DefaultGraphBuilder::class);

        $this->app->singleton(GraphCache::class, static function (Application $app): GraphCache {
            /** @var Repository $config */
            $config = $app->make(Repository::class);

            $enabled = (bool) $config->get('filament-dependency-graph.cache.enabled', true);

            if (! $enabled || $app->environment('testing')) {
                return new NullGraphCache;
            }

            $store = $config->get('filament-dependency-graph.cache.store');
            $ttl = $config->get('filament-dependency-graph.cache.ttl', 3600);

            return new LaravelGraphCache(
                cache: $app->make(CacheFactory::class),
                store: is_string($store) && $store !== '' ? $store : null,
                defaultTtlSeconds: is_numeric($ttl) ? (int) $ttl : null,
            );
        });

        $this->app->singleton(ExportManager::class, static function (Application $app): ExportManager {
            /** @var Repository $config */
            $config = $app->make(Repository::class);

            $manager = new ExportManager;

            if ($config->get('filament-dependency-graph.exports.json', true)) {
                $manager->register(new JsonGraphExporter);
            }

            if ($config->get('filament-dependency-graph.exports.mermaid', true)) {
                $manager->register(new MermaidGraphExporter);
            }

            return $manager;
        });

        $this->app->singleton(DefaultNodeInspector::class, static function (): DefaultNodeInspector {
            return new DefaultNodeInspector([
                new ModelInspector,
                new ResourceInspector,
                new LivewireComponentInspector,
                new PanelInspector,
            ]);
        });

        $this->app->singleton(NodeInspector::class, DefaultNodeInspector::class);
        $this->app->singleton(DependencyGraphManager::class, DefaultDependencyGraphManager::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-dependency-graph', __DIR__ . '/../dist/filament-dependency-graph.css'),
            AlpineComponent::make('dependency-graph', __DIR__ . '/../dist/components/dependency-graph.js'),
        ], package: 'laboiteacode/filament-dependency-graph');
    }
}
