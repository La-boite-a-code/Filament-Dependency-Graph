<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests;

use Illuminate\Contracts\Foundation\Application;
use LaBoiteACode\DependencyGraph\DependencyGraphServiceProvider;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Database\FixtureTables;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels\AdminPanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels\CustomerPanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels\OperationsPanelProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        FixtureTables::create();
    }

    protected function getPackageProviders($app): array
    {
        $filamentProviders = array_values(array_filter([
            'BladeUI\Heroicons\BladeHeroiconsServiceProvider',
            'BladeUI\Icons\BladeIconsServiceProvider',
            'Filament\Actions\ActionsServiceProvider',
            'Filament\FilamentServiceProvider',
            'Filament\Forms\FormsServiceProvider',
            'Filament\Infolists\InfolistsServiceProvider',
            'Filament\Notifications\NotificationsServiceProvider',
            'Filament\QueryBuilder\QueryBuilderServiceProvider',
            'Filament\Schemas\SchemasServiceProvider',
            'Filament\Support\SupportServiceProvider',
            'Filament\Tables\TablesServiceProvider',
            'Filament\Widgets\WidgetsServiceProvider',
            'Livewire\LivewireServiceProvider',
        ], 'class_exists'));

        return [
            ...$filamentProviders,
            DependencyGraphServiceProvider::class,
            AdminPanelProvider::class,
            OperationsPanelProvider::class,
            CustomerPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        tap($app['config'], static function ($config): void {
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);

            $config->set('filament-dependency-graph.model_paths', [
                __DIR__ . '/Fixtures/Models',
            ]);

            $config->set('filament-dependency-graph.model_namespaces', [
                'LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Models\\',
            ]);
        });
    }

    /**
     * Discovery context pointing at the fixture domain. The package root is
     * used as base path so fixture models count as application-owned.
     */
    public function fixtureContext(mixed ...$overrides): DiscoveryContext
    {
        $defaults = [
            'modelPaths' => [__DIR__ . '/Fixtures/Models'],
            'modelNamespaces' => ['LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Models\\'],
            'basePath' => dirname(__DIR__),
            'vendorPath' => dirname(__DIR__) . '/vendor',
        ];

        /** @var array<string, mixed> $arguments */
        $arguments = [...$defaults, ...$overrides];

        return new DiscoveryContext(...$arguments);
    }
}
