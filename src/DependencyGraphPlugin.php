<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Config\Repository;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Contracts\GraphExporter;
use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Export\ExportManager;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;
use LaBoiteACode\DependencyGraph\Inspection\DefaultNodeInspector;
use Throwable;

class DependencyGraphPlugin implements Plugin
{
    public const ID = 'filament-dependency-graph';

    protected ?Closure $visible = null;

    protected ?GraphScope $defaultScope = null;

    protected ?int $defaultDepth = null;

    protected ?bool $laravelScopeAllowed = null;

    protected ?bool $vendorModelsScanned = null;

    protected ?bool $panelNodesShown = null;

    protected ?bool $resourceNodesShown = null;

    /** @var list<class-string> */
    protected array $excludedModels = [];

    /** @var list<string> */
    protected array $modelPaths = [];

    /** @var list<string> */
    protected array $modelNamespaces = [];

    /** @var list<GraphExporter> */
    protected array $exporters = [];

    /** @var list<NodeInspector> */
    protected array $inspectors = [];

    public static function make(): static
    {
        /** @var static $plugin */
        $plugin = app(static::class);

        return $plugin;
    }

    /**
     * The plugin instance registered in the current Filament panel, if any.
     */
    public static function current(): ?self
    {
        try {
            $plugin = app(FilamentAdapter::class)->currentPanel()?->getPlugin(self::ID);
        } catch (Throwable) {
            return null;
        }

        return $plugin instanceof self ? $plugin : null;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function visible(Closure $callback): static
    {
        $this->visible = $callback;

        return $this;
    }

    public function defaultScope(GraphScope $scope): static
    {
        $this->defaultScope = $scope;

        return $this;
    }

    public function defaultDepth(int $depth): static
    {
        $this->defaultDepth = $depth;

        return $this;
    }

    public function allowLaravelScope(bool $condition = true): static
    {
        $this->laravelScopeAllowed = $condition;

        return $this;
    }

    public function scanVendorModels(bool $condition = true): static
    {
        $this->vendorModelsScanned = $condition;

        return $this;
    }

    public function showPanelNodes(bool $condition = true): static
    {
        $this->panelNodesShown = $condition;

        return $this;
    }

    public function showResourceNodes(bool $condition = true): static
    {
        $this->resourceNodesShown = $condition;

        return $this;
    }

    /**
     * @param  list<class-string>  $models
     */
    public function excludeModels(array $models): static
    {
        $this->excludedModels = [...$this->excludedModels, ...$models];

        return $this;
    }

    public function registerModelPath(string $path): static
    {
        $this->modelPaths[] = $path;

        return $this;
    }

    public function registerModelNamespace(string $namespace): static
    {
        $this->modelNamespaces[] = $namespace;

        return $this;
    }

    /**
     * @param  list<GraphExporter>  $exporters
     */
    public function exporters(array $exporters): static
    {
        $this->exporters = [...$this->exporters, ...$exporters];

        return $this;
    }

    public function registerExporter(GraphExporter $exporter): static
    {
        $this->exporters[] = $exporter;

        return $this;
    }

    public function registerInspector(NodeInspector $inspector): static
    {
        $this->inspectors[] = $inspector;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            DependencyGraphPage::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        $this->applyConfigurationOverrides();

        $exports = app(ExportManager::class);

        foreach ($this->exporters as $exporter) {
            $exports->register($exporter);
        }

        $inspector = app(DefaultNodeInspector::class);

        foreach ($this->inspectors as $custom) {
            $inspector->prepend($custom);
        }
    }

    /**
     * Whether the dependency graph page is visible for the current request.
     */
    public function isVisible(): bool
    {
        /** @var Repository $config */
        $config = app('config');

        if (! $config->get('filament-dependency-graph.enabled', true)) {
            return false;
        }

        if ($this->visible instanceof Closure) {
            return (bool) call_user_func($this->visible);
        }

        if ($config->get('filament-dependency-graph.authorization.local_only', true)) {
            return app()->isLocal();
        }

        return true;
    }

    /**
     * Plugin options are pushed into the configuration repository so the
     * application layer reads a single source of truth.
     */
    protected function applyConfigurationOverrides(): void
    {
        /** @var Repository $config */
        $config = app('config');

        if ($this->defaultScope !== null) {
            $config->set('filament-dependency-graph.default_scope', $this->defaultScope);
        }

        if ($this->defaultDepth !== null) {
            $config->set('filament-dependency-graph.graph.default_depth', $this->defaultDepth);
        }

        if ($this->laravelScopeAllowed !== null) {
            $config->set('filament-dependency-graph.laravel_scope_enabled', $this->laravelScopeAllowed);
        }

        if ($this->vendorModelsScanned !== null) {
            $config->set('filament-dependency-graph.vendor_models.enabled', $this->vendorModelsScanned);
        }

        if ($this->panelNodesShown !== null) {
            $config->set('filament-dependency-graph.graph.show_panel_nodes', $this->panelNodesShown);
        }

        if ($this->resourceNodesShown !== null) {
            $config->set('filament-dependency-graph.graph.show_resource_nodes', $this->resourceNodesShown);
        }

        $this->mergeConfigList($config, 'exclude.classes', $this->excludedModels);
        $this->mergeConfigList($config, 'model_paths', $this->modelPaths);
        $this->mergeConfigList($config, 'model_namespaces', $this->modelNamespaces);
    }

    /**
     * @param  list<string>  $additions
     */
    protected function mergeConfigList(Repository $config, string $key, array $additions): void
    {
        if ($additions === []) {
            return;
        }

        $current = $config->get('filament-dependency-graph.' . $key, []);
        $current = is_array($current) ? $current : [];

        $config->set(
            'filament-dependency-graph.' . $key,
            array_values(array_unique([...$current, ...$additions])),
        );
    }
}
