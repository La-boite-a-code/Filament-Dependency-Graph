<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Config\Repository;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Contracts\GraphExporter;
use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Export\ExportManager;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;
use LaBoiteACode\DependencyGraph\Inspection\DefaultNodeInspector;
use Throwable;
use UnitEnum;

class DependencyGraphPlugin implements Plugin
{
    public const ID = 'filament-dependency-graph';

    protected ?Closure $visible = null;

    protected string|Closure|null $navigationLabel = null;

    protected ?string $navigationIcon = null;

    protected ?string $activeNavigationIcon = null;

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?string $navigationParentItem = null;

    protected ?Closure $navigationBadge = null;

    protected ?bool $navigationRegistered = null;

    protected ?string $pageSlug = null;

    protected ?string $pageCluster = null;

    protected Width|string|null $maxContentWidth = null;

    protected ?GraphScope $defaultScope = null;

    protected ?int $defaultDepth = null;

    protected ?bool $laravelScopeAllowed = null;

    protected ?bool $vendorModelsScanned = null;

    protected ?bool $livewireComponentsScanned = null;

    protected ?bool $panelNodesShown = null;

    protected ?bool $resourceNodesShown = null;

    /** @var list<class-string> */
    protected array $excludedModels = [];

    /** @var list<string> */
    protected array $modelPaths = [];

    /** @var list<string> */
    protected array $modelNamespaces = [];

    /** @var list<string> */
    protected array $livewirePaths = [];

    /** @var list<string> */
    protected array $livewireNamespaces = [];

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

    /**
     * Alias of visible(), consistent with the other La Boite a Code plugins.
     */
    public function canAccessUsing(Closure $callback): static
    {
        return $this->visible($callback);
    }

    public function navigationLabel(string|Closure $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function activeNavigationIcon(string $icon): static
    {
        $this->activeNavigationIcon = $icon;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|Closure $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationParentItem(string $item): static
    {
        $this->navigationParentItem = $item;

        return $this;
    }

    /**
     * The closure returns the badge content, or null to hide it.
     */
    public function navigationBadge(Closure $badge): static
    {
        $this->navigationBadge = $badge;

        return $this;
    }

    public function registerNavigation(bool $condition = true): static
    {
        $this->navigationRegistered = $condition;

        return $this;
    }

    public function slug(string $slug): static
    {
        $this->pageSlug = $slug;

        return $this;
    }

    /**
     * @param  class-string  $cluster
     */
    public function cluster(string $cluster): static
    {
        $this->pageCluster = $cluster;

        return $this;
    }

    public function maxContentWidth(Width|string $width): static
    {
        $this->maxContentWidth = $width;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        $label = $this->navigationLabel instanceof Closure
            ? call_user_func($this->navigationLabel)
            : $this->navigationLabel;

        return is_string($label) && $label !== '' ? $label : null;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function getActiveNavigationIcon(): ?string
    {
        return $this->activeNavigationIcon;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        $group = $this->navigationGroup instanceof Closure
            ? call_user_func($this->navigationGroup)
            : $this->navigationGroup;

        if ($group instanceof UnitEnum) {
            return $group;
        }

        return is_string($group) && $group !== '' ? $group : null;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function getNavigationParentItem(): ?string
    {
        return $this->navigationParentItem;
    }

    public function getNavigationBadge(): ?string
    {
        if (! $this->navigationBadge instanceof Closure) {
            return null;
        }

        $badge = call_user_func($this->navigationBadge);

        if (is_string($badge) && $badge !== '') {
            return $badge;
        }

        return is_int($badge) ? (string) $badge : null;
    }

    public function isNavigationRegistered(): ?bool
    {
        return $this->navigationRegistered;
    }

    public function getPageSlug(): ?string
    {
        return $this->pageSlug;
    }

    public function getPageCluster(): ?string
    {
        return $this->pageCluster;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return $this->maxContentWidth;
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

    public function scanLivewireComponents(bool $condition = true): static
    {
        $this->livewireComponentsScanned = $condition;

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

    public function registerLivewirePath(string $path): static
    {
        $this->livewirePaths[] = $path;

        return $this;
    }

    public function registerLivewireNamespace(string $namespace): static
    {
        $this->livewireNamespaces[] = $namespace;

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

        if ($this->livewireComponentsScanned !== null) {
            $config->set('filament-dependency-graph.livewire.enabled', $this->livewireComponentsScanned);
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
        $this->mergeConfigList($config, 'livewire.paths', $this->livewirePaths);
        $this->mergeConfigList($config, 'livewire.namespaces', $this->livewireNamespaces);
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
