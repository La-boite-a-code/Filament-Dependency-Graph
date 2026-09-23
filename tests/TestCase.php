<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use LaBoiteACode\DependencyGraph\DependencyGraphServiceProvider;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Database\FixtureTables;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderArchived;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners\SendOrderConfirmation;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners\UpdateInventory;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Policies\CatalogPolicy;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\StandaloneCounter;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels\AdminPanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels\CustomerPanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels\OperationsPanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Alert;
use Livewire\Livewire;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        FixtureTables::create();

        Gate::policy(Product::class, CatalogPolicy::class);
        Event::listen(OrderPlaced::class, SendOrderConfirmation::class);
        Event::listen(OrderPlaced::class, [UpdateInventory::class, 'handle']);
        Event::listen(OrderArchived::class, UpdateInventory::class . '@release');
        Event::listen(OrderArchived::class, static function (): void {});

        Blade::component('alert', Alert::class);
        Blade::componentNamespace('LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\View\\Components\\Shop', 'shop');
        Livewire::component('standalone-counter', StandaloneCounter::class);
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        require __DIR__ . '/Fixtures/Http/routes.php';
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

            $config->set('filament-dependency-graph.livewire.paths', [
                __DIR__ . '/Fixtures/Livewire',
            ]);

            $config->set('filament-dependency-graph.livewire.namespaces', [
                'LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Livewire\\',
            ]);

            $config->set('filament-dependency-graph.http.controller_namespaces', [
                'LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Http\\Controllers\\',
            ]);

            $config->set('filament-dependency-graph.http.application_namespaces', [
                'LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\',
            ]);

            $config->set('view.paths', [__DIR__ . '/Fixtures/views']);
            $config->set('filament-dependency-graph.views.blade_component_paths', [__DIR__ . '/Fixtures/View/Components']);
            $config->set('filament-dependency-graph.views.filament_paths', [__DIR__ . '/Fixtures/FilamentViews']);
            $config->set('filament-dependency-graph.views.mail_paths', [
                __DIR__ . '/Fixtures/Http/Mail',
                __DIR__ . '/Fixtures/Http/Notifications',
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
            'livewirePaths' => [__DIR__ . '/Fixtures/Livewire'],
            'livewireNamespaces' => ['LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Livewire\\'],
            'basePath' => dirname(__DIR__),
            'vendorPath' => dirname(__DIR__) . '/vendor',
            'httpControllerNamespaces' => ['LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Http\\Controllers\\'],
            'httpApplicationNamespaces' => ['LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\'],
            'bladeComponentPaths' => [__DIR__ . '/Fixtures/View/Components'],
            'filamentViewPaths' => [__DIR__ . '/Fixtures/FilamentViews'],
            'mailPaths' => [__DIR__ . '/Fixtures/Http/Mail', __DIR__ . '/Fixtures/Http/Notifications'],
        ];

        /** @var array<string, mixed> $arguments */
        $arguments = [...$defaults, ...$overrides];

        return new DiscoveryContext(...$arguments);
    }
}
