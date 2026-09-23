<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Clusters\Cluster;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use LaBoiteACode\DependencyGraph\Application\SearchDependencyGraph;
use LaBoiteACode\DependencyGraph\Contracts\DependencyGraphManager;
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Inspection\DefaultNodeInspector;
use LaBoiteACode\DependencyGraph\Inspection\EdgeInspector;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\SearchNormalizer;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

class DependencyGraphPage extends Page implements HasTable
{
    use InteractsWithTable;

    private const INSPECTOR_MODAL_ID = 'fdg-inspector';

    private const TABLE_DATASETS = ['models', 'livewire_components', 'relations', 'resources'];

    private const HTTP_TABLE_DATASETS = ['routes', 'events', 'dispatches', 'models'];

    private const VIEW_TABLE_DATASETS = ['views', 'components', 'externals', 'livewire_components'];

    protected string $view = 'filament-dependency-graph::page';

    protected static ?string $slug = 'dependency-graph';

    #[Url(as: 'scope')]
    public string $scope = 'filament';

    /** @var list<string> */
    #[Url(as: 'panels')]
    public array $panelFilter = [];

    #[Url(as: 'view')]
    public string $activeView = 'graph';

    #[Url]
    public ?string $focus = null;

    #[Url]
    public ?int $depth = null;

    #[Url]
    public string $direction = 'both';

    public ?string $selectedNodeId = null;

    public ?string $selectedEdgeId = null;

    public string $search = '';

    /** @var list<string> */
    public array $hiddenNodeTypes = [];

    /** @var list<string> */
    public array $hiddenRelationTypes = [];

    public string $namespaceFilter = '';

    public string $ownershipFilter = 'all';

    public bool $showOrphans = true;

    public bool $onlyOrphans = false;

    public bool $onlyCycles = false;

    public bool $onlyWithoutResource = false;

    public string $graphLayout = 'hierarchical';

    #[Url(as: 'dataset')]
    public string $tableDataset = 'models';

    #[Url(as: 'middleware')]
    public string $middlewareFilter = '';

    protected ?Graph $memoizedGraph = null;

    protected ?Graph $memoizedSearchGraph = null;

    protected bool $scopeSwitchesDatasets = false;

    public static function canAccess(): bool
    {
        return DependencyGraphPlugin::current()?->isVisible() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $registered = DependencyGraphPlugin::current()?->isNavigationRegistered()
            ?? static::packageConfig('navigation.register');

        if ($registered === false) {
            return false;
        }

        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        $label = DependencyGraphPlugin::current()?->getNavigationLabel()
            ?? static::packageConfigString('navigation.label');

        return $label ?? __('filament-dependency-graph::graph.navigation_label');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        $icon = DependencyGraphPlugin::current()?->getNavigationIcon()
            ?? static::packageConfigString('navigation.icon');

        return $icon ?? 'heroicon-o-share';
    }

    public static function getActiveNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        $icon = DependencyGraphPlugin::current()?->getActiveNavigationIcon()
            ?? static::packageConfigString('navigation.active_icon');

        return $icon ?? static::getNavigationIcon();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = DependencyGraphPlugin::current()?->getNavigationGroup();

        if ($group !== null) {
            return $group;
        }

        $configured = static::packageConfig('navigation.group');

        if ($configured instanceof UnitEnum) {
            return $configured;
        }

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    public static function getNavigationParentItem(): ?string
    {
        return DependencyGraphPlugin::current()?->getNavigationParentItem()
            ?? static::packageConfigString('navigation.parent_item');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = DependencyGraphPlugin::current()?->getNavigationSort()
            ?? static::packageConfig('navigation.sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationBadge(): ?string
    {
        return DependencyGraphPlugin::current()?->getNavigationBadge();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        $slug = DependencyGraphPlugin::current()?->getPageSlug()
            ?? static::packageConfigString('page.slug');

        return $slug ?? parent::getSlug($panel);
    }

    /**
     * @return class-string<Cluster>|null
     */
    public static function getCluster(): ?string
    {
        $cluster = DependencyGraphPlugin::current()?->getPageCluster()
            ?? static::packageConfigString('page.cluster');

        if ($cluster === null || ! is_subclass_of($cluster, Cluster::class)) {
            return null;
        }

        return $cluster;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        $width = DependencyGraphPlugin::current()?->getMaxContentWidth()
            ?? static::packageConfigString('page.max_content_width');

        if ($width instanceof Width) {
            return $width;
        }

        if ($width !== null) {
            return Width::tryFrom($width) ?? $width;
        }

        return parent::getMaxContentWidth();
    }

    public function getTitle(): string
    {
        $label = DependencyGraphPlugin::current()?->getNavigationLabel()
            ?? static::packageConfigString('navigation.label');

        return $label ?? __('filament-dependency-graph::graph.title');
    }

    protected static function packageConfig(string $key): mixed
    {
        return app(Repository::class)->get('filament-dependency-graph.' . $key);
    }

    protected static function packageConfigString(string $key): ?string
    {
        $value = static::packageConfig($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function mount(): void
    {
        $config = $this->configRepository();

        $defaultScope = $config->get('filament-dependency-graph.default_scope', GraphScope::Filament);

        if ($defaultScope instanceof GraphScope) {
            $defaultScope = $defaultScope->value;
        }

        $this->scope = GraphScope::tryFrom($this->scope)->value
            ?? (is_string($defaultScope) ? $defaultScope : GraphScope::Filament->value);
        $this->scope = $this->currentScope()->value;

        $this->direction = TraversalDirection::tryFrom($this->direction)->value
            ?? (string) $config->get('filament-dependency-graph.graph.default_direction', 'both');

        $this->showOrphans = (bool) $config->get('filament-dependency-graph.graph.show_orphans', true);
        $this->graphLayout = (string) $config->get('filament-dependency-graph.graph.default_layout', 'hierarchical');
        $this->activeView = in_array($this->activeView, ['graph', 'tree', 'table'], true)
            ? $this->activeView
            : 'graph';
        $this->tableDataset = in_array($this->tableDataset, $this->availableTableDatasets(), true)
            ? $this->tableDataset
            : $this->availableTableDatasets()[0];

        if (! $config->get('filament-dependency-graph.graph.show_panel_nodes', true)) {
            $this->hiddenNodeTypes[] = NodeType::Panel->value;
        }

        if (! $config->get('filament-dependency-graph.graph.show_resource_nodes', true)) {
            $this->hiddenNodeTypes[] = NodeType::Resource->value;
        }
    }

    public function selectNode(string $nodeId): void
    {
        $this->selectedNodeId = $nodeId;
        $this->selectedEdgeId = null;

        $this->dispatch('open-modal', id: self::INSPECTOR_MODAL_ID);
    }

    public function selectEdge(string $edgeId): void
    {
        $this->selectedEdgeId = $edgeId;
        $this->selectedNodeId = null;

        $this->dispatch('open-modal', id: self::INSPECTOR_MODAL_ID);
    }

    public function clearSelection(): void
    {
        if ($this->selectedNodeId === null && $this->selectedEdgeId === null && $this->focus !== null) {
            $this->focus = null;
            $this->dispatch('close-modal', id: self::INSPECTOR_MODAL_ID);
            $this->dispatch('dependency-graph-clear-selection');

            return;
        }

        $this->selectedNodeId = null;
        $this->selectedEdgeId = null;

        $this->dispatch('close-modal', id: self::INSPECTOR_MODAL_ID);
        $this->dispatch('dependency-graph-clear-selection');
    }

    public function focusOnNode(?string $nodeId = null): void
    {
        $nodeId ??= $this->selectedNodeId;

        if ($nodeId === null) {
            return;
        }

        $this->focus = $nodeId;
        $this->selectedNodeId = $nodeId;
        $this->selectedEdgeId = null;

        $this->depth ??= (int) $this->configRepository()->get('filament-dependency-graph.graph.default_depth', 2);
    }

    public function clearFocus(): void
    {
        $this->focus = null;
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['graph', 'tree', 'table'], true)) {
            $this->activeView = $view;
        }
    }

    public function updatingScope(mixed $scope): void
    {
        $next = GraphScope::tryFrom(is_string($scope) ? $scope : '') ?? GraphScope::Filament;

        $this->scopeSwitchesDatasets = $this->datasetsFor($next) !== $this->availableTableDatasets();
    }

    /**
     * Switching scope drops the filters and datasets that only make sense
     * in the previous one.
     */
    public function updatedScope(): void
    {
        $this->scope = $this->currentScope()->value;
        $this->middlewareFilter = '';

        if ($this->scopeSwitchesDatasets || ! in_array($this->tableDataset, $this->availableTableDatasets(), true)) {
            $this->tableDataset = $this->availableTableDatasets()[0];
            $this->tableSearch = '';
            $this->tableSort = null;
        }

        $this->forgetMemoizedGraphs();
        $this->resetTable();
    }

    public function setTableDataset(string $dataset): void
    {
        if (! in_array($dataset, $this->availableTableDatasets(), true)) {
            return;
        }

        $this->tableDataset = $dataset;
        $this->tableSearch = '';
        $this->tableSort = null;

        $this->resetTable();
    }

    public function toggleNodeType(string $type): void
    {
        if (in_array($type, $this->hiddenNodeTypes, true)) {
            $this->hiddenNodeTypes = array_values(array_diff($this->hiddenNodeTypes, [$type]));

            return;
        }

        $this->hiddenNodeTypes[] = $type;
    }

    public function toggleRelationType(string $type): void
    {
        if (in_array($type, $this->hiddenRelationTypes, true)) {
            $this->hiddenRelationTypes = array_values(array_diff($this->hiddenRelationTypes, [$type]));

            return;
        }

        $this->hiddenRelationTypes[] = $type;
    }

    /**
     * Restores every default: scope, filters, focus, depth, selection.
     */
    public function resetGraph(): void
    {
        $this->panelFilter = [];
        $this->focus = null;
        $this->depth = null;
        $this->selectedNodeId = null;
        $this->selectedEdgeId = null;
        $this->search = '';
        $this->hiddenNodeTypes = [];
        $this->hiddenRelationTypes = [];
        $this->namespaceFilter = '';
        $this->ownershipFilter = 'all';
        $this->onlyOrphans = false;
        $this->onlyCycles = false;
        $this->onlyWithoutResource = false;
        $this->middlewareFilter = '';
        $this->tableSearch = '';
        $this->tableSort = null;

        $config = $this->configRepository();

        $scope = $config->get('filament-dependency-graph.default_scope', GraphScope::Filament);
        $this->scope = $scope instanceof GraphScope ? $scope->value : (string) $scope;
        $this->scope = $this->currentScope()->value;
        $this->tableDataset = $this->availableTableDatasets()[0];
        $this->direction = (string) $config->get('filament-dependency-graph.graph.default_direction', 'both');
        $this->showOrphans = (bool) $config->get('filament-dependency-graph.graph.show_orphans', true);
        $this->graphLayout = (string) $config->get('filament-dependency-graph.graph.default_layout', 'hierarchical');

        $this->forgetMemoizedGraphs();
        $this->resetTable();
        $this->dispatch('close-modal', id: self::INSPECTOR_MODAL_ID);
        $this->dispatch('dependency-graph-clear-selection');
    }

    /**
     * Selecting a search result clears transient filters that could hide the
     * node, selects it and lets the frontend center on it.
     */
    public function selectSearchResult(string $nodeId): void
    {
        $this->search = '';
        $this->focus = null;
        $this->onlyOrphans = false;
        $this->onlyCycles = false;
        $this->onlyWithoutResource = false;
        $this->namespaceFilter = '';
        $this->ownershipFilter = 'all';
        $this->middlewareFilter = '';
        $this->hiddenNodeTypes = [];
        $this->hiddenRelationTypes = [];

        $this->forgetMemoizedGraphs();

        $this->selectNode($nodeId);

        $this->dispatch('dependency-graph-center', nodeId: $nodeId);
    }

    public function export(string $format): StreamedResponse
    {
        $content = $this->manager()->export($format, $this->graphQuery());

        $extension = $format === 'mermaid' ? 'mmd' : $format;
        $filename = 'dependency-graph.' . $extension;

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename);
    }

    /**
     * @return array{graph: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}, stats: array<string, int>, error: string|null}
     */
    public function getGraphPayload(): array
    {
        try {
            $graph = $this->currentGraph();

            return [
                'graph' => $this->rendererGraph($graph),
                'stats' => [
                    'nodes' => $graph->nodeCount(),
                    'edges' => $graph->edgeCount(),
                ],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'graph' => ['nodes' => [], 'edges' => []],
                'stats' => ['nodes' => 0, 'edges' => 0],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * What the renderer draws, without the metadata only the inspector
     * reads: the payload is embedded in the page on every update.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    protected function rendererGraph(Graph $graph): array
    {
        return [
            'nodes' => array_map(static fn (Node $node): array => [
                'id' => $node->id->value,
                'type' => $node->type->value,
                'label' => $node->label,
                'subtitle' => $node->subtitle,
                'badges' => $node->badges,
                'status' => $node->status->value,
                'metadata' => ['kind' => $node->metadata['kind'] ?? null],
            ], $graph->nodes),
            'edges' => array_map(static fn (Edge $edge): array => [
                'id' => $edge->id->value,
                'type' => $edge->type->value,
                'source' => $edge->source->value,
                'target' => $edge->target->value,
                'label' => $edge->label,
            ], $graph->edges),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSearchResults(): array
    {
        if (trim($this->search) === '') {
            return [];
        }

        try {
            $graph = $this->searchableGraph();
        } catch (Throwable) {
            return [];
        }

        $results = app(SearchDependencyGraph::class)
            ->execute($graph, $this->search, 15);

        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result->type->value][] = $result->toArray();
        }

        return array_map(
            static fn (string $type, array $items): array => ['type' => $type, 'results' => $items],
            array_keys($grouped),
            array_values($grouped),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInspection(): ?array
    {
        try {
            $graph = $this->currentGraph();
        } catch (Throwable) {
            return null;
        }

        if ($this->selectedEdgeId !== null) {
            $edge = $graph->edge($this->selectedEdgeId);

            return $edge === null ? null : app(EdgeInspector::class)->inspect($edge, $graph)->toArray();
        }

        if ($this->selectedNodeId !== null) {
            $node = $graph->node($this->selectedNodeId);

            return $node === null ? null : app(DefaultNodeInspector::class)->inspect($node, $graph)->toArray();
        }

        return null;
    }

    /**
     * Cycle-safe tree representation of the current graph.
     *
     * @return list<array<string, mixed>>
     */
    public function getTree(): array
    {
        try {
            $graph = $this->currentGraph();
        } catch (Throwable) {
            return [];
        }

        $maxDepth = $this->depth ?? (int) $this->configRepository()->get('filament-dependency-graph.graph.default_depth', 2);
        $maxDepth = max($maxDepth, 1);
        $http = $this->currentScope() === GraphScope::Http;
        $views = $this->currentScope() === GraphScope::Views;

        if ($http || $views) {
            // A route leads through its controller to models, dispatches and
            // listeners, a page through its layout to partials and
            // components: four levels are needed to read the whole chain.
            $maxDepth = max($maxDepth, 4);
        }

        $hasSelection = $this->selectedNodeId !== null && $graph->hasNode($this->selectedNodeId);

        if ($http && ! $hasSelection) {
            return $this->httpTree($graph, $maxDepth);
        }

        if ($views && ! $hasSelection) {
            return $this->viewsTree($graph, $maxDepth);
        }

        $roots = [];

        if ($this->selectedNodeId !== null && $graph->hasNode($this->selectedNodeId)) {
            $roots[] = $this->selectedNodeId;
        } else {
            foreach ($graph->nodesOfType(NodeType::Panel) as $panel) {
                $roots[] = $panel->id->value;
            }

            foreach ($graph->nodesOfType(NodeType::LivewireComponent) as $component) {
                $roots[] = $component->id->value;
            }

            if ($roots === []) {
                foreach ($graph->nodesOfType(NodeType::Model) as $model) {
                    if ($graph->incomingEdges($model->id) === []) {
                        $roots[] = $model->id->value;
                    }
                }
            }

            if ($roots === []) {
                foreach ($graph->nodes as $node) {
                    $roots[] = $node->id->value;
                }
            }
        }

        $tree = [];

        foreach ($roots as $rootId) {
            $visited = [];
            $root = $graph->node($rootId);
            $follow = match (true) {
                $http && $root !== null => $this->httpTreeFilter($graph, $root),
                $views => $this->viewsTreeFilter(),
                default => null,
            };

            $tree[] = $this->treeNode($graph, $rootId, null, $maxDepth + 1, $visited, $follow);
        }

        return array_values(array_filter($tree));
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(
                fn (
                    ?string $search,
                    ?string $sortColumn,
                    ?string $sortDirection,
                    int $page,
                    int $recordsPerPage,
                ): LengthAwarePaginator => $this->getDatasetTableRecords(
                    search: $search,
                    sortColumn: $sortColumn,
                    sortDirection: $sortDirection,
                    page: $page,
                    recordsPerPage: $recordsPerPage,
                ),
            )
            ->columns($this->getDatasetTableColumns())
            ->heading(__('filament-dependency-graph::graph.table.' . $this->tableDataset))
            ->description(fn (): string => __('filament-dependency-graph::graph.table.items', [
                'count' => count($this->getTables()[$this->tableDataset] ?? []),
            ]))
            ->searchable()
            ->searchDebounce('300ms')
            ->searchPlaceholder(__('filament-dependency-graph::graph.table.search_placeholder'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->recordAction(fn (): string => $this->tableDataset === 'relations' ? 'selectEdge' : 'selectNode')
            ->recordClasses('fdg-native-table-record')
            ->emptyStateHeading(__('filament-dependency-graph::graph.table.empty'))
            ->emptyStateIcon('heroicon-o-circle-stack')
            ->columnManagerColumns(2);
    }

    /**
     * @return array<string, array{label: string, icon: string, count: int}>
     */
    public function getTableDatasetOptions(): array
    {
        $tables = $this->getTables();
        $icons = [
            'models' => 'heroicon-m-circle-stack',
            'livewire_components' => 'heroicon-m-bolt',
            'relations' => 'heroicon-m-arrows-right-left',
            'resources' => 'heroicon-m-rectangle-stack',
            'routes' => 'heroicon-m-globe-alt',
            'events' => 'heroicon-m-megaphone',
            'dispatches' => 'heroicon-m-paper-airplane',
            'views' => 'heroicon-m-document-text',
            'components' => 'heroicon-m-puzzle-piece',
            'externals' => 'heroicon-m-archive-box',
        ];

        $options = [];

        foreach ($this->availableTableDatasets() as $dataset) {
            $options[$dataset] = [
                'label' => __('filament-dependency-graph::graph.table.' . $dataset),
                'icon' => $icons[$dataset],
                'count' => count($tables[$dataset]),
            ];
        }

        return $options;
    }

    /**
     * @return non-empty-list<string>
     */
    protected function availableTableDatasets(): array
    {
        return $this->datasetsFor($this->currentScope());
    }

    /**
     * @return non-empty-list<string>
     */
    protected function datasetsFor(GraphScope $scope): array
    {
        return match ($scope) {
            GraphScope::Http => self::HTTP_TABLE_DATASETS,
            GraphScope::Views => self::VIEW_TABLE_DATASETS,
            default => self::TABLE_DATASETS,
        };
    }

    /**
     * @return array<int, IconColumn|TextColumn>
     */
    protected function getDatasetTableColumns(): array
    {
        return match ($this->tableDataset) {
            'views' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.view_name'))
                    ->description(fn (array $record): string => (string) ($record['file'] ?? ''))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('filament-dependency-graph::graph.table.kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('filament-dependency-graph::graph.view_kinds.' . $state))
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('used_by')
                    ->label(__('filament-dependency-graph::graph.table.used_by'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uses')
                    ->label(__('filament-dependency-graph::graph.table.uses'))
                    ->numeric()
                    ->sortable(),
                $this->statusTableColumn(),
            ],
            'components' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.component'))
                    ->description(fn (array $record): string => (string) ($record['detail'] ?? ''))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('filament-dependency-graph::graph.table.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('filament-dependency-graph::graph.inspector.types.' . $state))
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('view')
                    ->label(__('filament-dependency-graph::graph.table.view'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('-'),
                TextColumn::make('used_by')
                    ->label(__('filament-dependency-graph::graph.table.used_by'))
                    ->numeric()
                    ->sortable(),
                $this->statusTableColumn(),
            ],
            'externals' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('package')
                    ->label(__('filament-dependency-graph::graph.table.package'))
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('missing')
                    ->label(__('filament-dependency-graph::graph.table.missing'))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('used_by')
                    ->label(__('filament-dependency-graph::graph.table.used_by'))
                    ->numeric()
                    ->sortable(),
            ],
            'routes' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.route'))
                    ->description(fn (array $record): string => (string) ($record['name'] ?? ''))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('action')
                    ->label(__('filament-dependency-graph::graph.table.action'))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('middleware')
                    ->label(__('filament-dependency-graph::graph.table.middleware'))
                    ->badge()
                    ->separator(', ')
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('form_requests')
                    ->label(__('filament-dependency-graph::graph.table.form_requests'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('models')
                    ->label(__('filament-dependency-graph::graph.table.models'))
                    ->placeholder('-')
                    ->toggleable(),
                $this->statusTableColumn(),
            ],
            'events' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.event'))
                    ->description(fn (array $record): string => (string) ($record['namespace'] ?? ''))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('listeners')
                    ->label(__('filament-dependency-graph::graph.table.listeners'))
                    ->placeholder('-'),
                TextColumn::make('queued_listeners')
                    ->label(__('filament-dependency-graph::graph.table.queued_listeners'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dispatched_by')
                    ->label(__('filament-dependency-graph::graph.table.dispatched_by'))
                    ->placeholder(__('filament-dependency-graph::graph.table.not_dispatched')),
                $this->statusTableColumn(),
            ],
            'dispatches' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.class'))
                    ->description(fn (array $record): string => (string) ($record['namespace'] ?? ''))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('filament-dependency-graph::graph.table.kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('filament-dependency-graph::graph.inspector.types.' . $state))
                    ->color('gray')
                    ->sortable(),
                IconColumn::make('queued')
                    ->label(__('filament-dependency-graph::graph.table.queued'))
                    ->boolean(),
                TextColumn::make('dispatched_by')
                    ->label(__('filament-dependency-graph::graph.table.dispatched_by'))
                    ->placeholder('-'),
                $this->statusTableColumn(),
            ],
            'livewire_components' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.component'))
                    ->description(fn (array $record): string => (string) ($record['alias'] ?? ''))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('view')
                    ->label(__('filament-dependency-graph::graph.table.view'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('models')
                    ->label(__('filament-dependency-graph::graph.table.models_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('properties')
                    ->label(__('filament-dependency-graph::graph.table.properties'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('methods')
                    ->label(__('filament-dependency-graph::graph.table.methods_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('used_in_views')
                    ->label(__('filament-dependency-graph::graph.table.used_in_views'))
                    ->numeric()
                    ->sortable()
                    ->visible(fn (): bool => $this->currentScope() === GraphScope::Views),
                $this->statusTableColumn(),
            ],
            'relations' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.source'))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('filament-dependency-graph::graph.table.method'))
                    ->fontFamily(FontFamily::Mono)
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('filament-dependency-graph::graph.table.type'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('target')
                    ->label(__('filament-dependency-graph::graph.table.target'))
                    ->weight(FontWeight::Medium)
                    ->sortable(),
                TextColumn::make('foreign_key')
                    ->label(__('filament-dependency-graph::graph.table.foreign_key'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pivot')
                    ->label(__('filament-dependency-graph::graph.table.pivot'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nullable')
                    ->label(__('filament-dependency-graph::graph.table.nullable'))
                    ->badge()
                    ->state(fn (array $record): string => $this->formatTableBoolean(
                        is_bool($record['nullable'] ?? null) ? $record['nullable'] : null,
                    ))
                    ->color(fn (array $record): string => match ($record['nullable'] ?? null) {
                        true => 'success',
                        false => 'gray',
                        default => 'warning',
                    }),
                $this->statusTableColumn(),
            ],
            'resources' => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.resource'))
                    ->description(fn (array $record): string => (string) ($record['model'] ?? ''))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('panels')
                    ->label(__('filament-dependency-graph::graph.table.panels'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('navigation_group')
                    ->label(__('filament-dependency-graph::graph.table.navigation_group'))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('pages')
                    ->label(__('filament-dependency-graph::graph.table.pages'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('relation_managers')
                    ->label(__('filament-dependency-graph::graph.table.relation_managers'))
                    ->numeric()
                    ->sortable(),
                $this->statusTableColumn(),
            ],
            default => [
                TextColumn::make('label')
                    ->label(__('filament-dependency-graph::graph.table.model'))
                    ->description(fn (array $record): string => (string) ($record['namespace'] ?? ''))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('table')
                    ->label(__('filament-dependency-graph::graph.table.database_table'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('resources')
                    ->label(__('filament-dependency-graph::graph.table.resources_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('livewire_components')
                    ->label(__('filament-dependency-graph::graph.table.livewire_components_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('outgoing')
                    ->label(__('filament-dependency-graph::graph.table.outgoing'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('incoming')
                    ->label(__('filament-dependency-graph::graph.table.incoming'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('soft_deletes')
                    ->label(__('filament-dependency-graph::graph.table.soft_deletes'))
                    ->boolean(),
                $this->statusTableColumn(),
            ],
        };
    }

    protected function statusTableColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('filament-dependency-graph::graph.table.status'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => __(
                'filament-dependency-graph::graph.table.statuses.' . $state,
            ))
            ->color(fn (string $state): string => match ($state) {
                'complete' => 'success',
                'partial' => 'warning',
                'failed' => 'danger',
                default => 'gray',
            });
    }

    protected function formatTableBoolean(?bool $value): string
    {
        return match ($value) {
            true => __('filament-dependency-graph::graph.table.yes'),
            false => __('filament-dependency-graph::graph.table.no'),
            null => __('filament-dependency-graph::graph.table.unknown'),
        };
    }

    /**
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    protected function getDatasetTableRecords(
        ?string $search,
        ?string $sortColumn,
        ?string $sortDirection,
        int $page,
        int $recordsPerPage,
    ): LengthAwarePaginator {
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($this->getTables()[$this->tableDataset] ?? []);

        if (filled($search)) {
            $normalizedSearch = SearchNormalizer::normalize((string) $search);

            $rows = $rows->filter(
                static fn (array $row): bool => collect($row)
                    ->filter(static fn (mixed $value): bool => is_scalar($value))
                    ->contains(
                        static fn (mixed $value): bool => str_contains(
                            SearchNormalizer::normalize((string) $value),
                            $normalizedSearch,
                        ),
                    ),
            );
        }

        $sortColumn ??= 'label';
        $sortDirection ??= 'asc';

        $rows = $rows->sortBy(
            static fn (array $row): string => SearchNormalizer::normalize((string) ($row[$sortColumn] ?? '')),
            SORT_NATURAL,
            $sortDirection === 'desc',
        );

        $total = $rows->count();
        $records = $rows
            ->forPage($page, $recordsPerPage)
            ->keyBy(static fn (array $row): string => (string) $row['id']);

        return new LengthAwarePaginator(
            items: $records,
            total: $total,
            perPage: $recordsPerPage,
            currentPage: $page,
            options: ['pageName' => $this->getTablePaginationPageName()],
        );
    }

    /**
     * @return array{models: list<array<string, mixed>>, relations: list<array<string, mixed>>, resources: list<array<string, mixed>>, livewire_components: list<array<string, mixed>>, routes: list<array<string, mixed>>, events: list<array<string, mixed>>, dispatches: list<array<string, mixed>>, views: list<array<string, mixed>>, components: list<array<string, mixed>>, externals: list<array<string, mixed>>}
     */
    public function getTables(): array
    {
        try {
            $graph = $this->currentGraph();
        } catch (Throwable) {
            return [
                'models' => [],
                'relations' => [],
                'resources' => [],
                'livewire_components' => [],
                'routes' => [],
                'events' => [],
                'dispatches' => [],
                'views' => [],
                'components' => [],
                'externals' => [],
            ];
        }

        $models = [];

        foreach ($graph->nodesOfType(NodeType::Model) as $node) {
            $outgoing = 0;
            $incoming = 0;
            $resourceCount = 0;
            $livewireComponentCount = 0;

            foreach ($graph->outgoingEdges($node->id) as $edge) {
                if ($edge->type === EdgeType::ModelRelation) {
                    $outgoing++;
                }
            }

            foreach ($graph->incomingEdges($node->id) as $edge) {
                if ($edge->type === EdgeType::ModelRelation) {
                    $incoming++;
                }

                if ($edge->type === EdgeType::ResourceUsesModel) {
                    $resourceCount++;
                }

                if ($edge->type === EdgeType::LivewireUsesModel) {
                    $livewireComponentCount++;
                }
            }

            $models[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'namespace' => $node->metadata['namespace'] ?? '',
                'table' => $node->metadata['table'] ?? '',
                'resources' => $resourceCount,
                'livewire_components' => $livewireComponentCount,
                'outgoing' => $outgoing,
                'incoming' => $incoming,
                'soft_deletes' => ($node->metadata['soft_deletes'] ?? false) === true,
                'status' => $node->status->value,
            ];
        }

        $relations = [];

        foreach ($graph->edgesOfType(EdgeType::ModelRelation) as $edge) {
            $relations[] = [
                'id' => $edge->id->value,
                'label' => $graph->node($edge->source)->label ?? $edge->source->value,
                'method' => $edge->label,
                'type' => $edge->metadata['relation_label'] ?? '',
                'target' => $graph->node($edge->target)->label ?? $edge->target->value,
                'foreign_key' => $edge->metadata['foreign_key'] ?? null,
                'pivot' => $edge->metadata['pivot_table'] ?? null,
                'nullable' => $edge->metadata['nullable'] ?? null,
                'status' => $edge->status->value,
            ];
        }

        $resources = [];

        foreach ($graph->nodesOfType(NodeType::Resource) as $node) {
            $panelIds = $node->metadata['panel_ids'] ?? [];
            $pages = $node->metadata['pages'] ?? [];
            $managers = $node->metadata['relation_managers'] ?? [];

            $resources[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'model' => $node->subtitle ?? '',
                'panels' => implode(', ', array_filter(is_array($panelIds) ? $panelIds : [], 'is_string')),
                'navigation_group' => $node->metadata['navigation_group'] ?? null,
                'pages' => is_array($pages) ? count($pages) : 0,
                'relation_managers' => is_array($managers) ? count($managers) : 0,
                'status' => $node->status->value,
            ];
        }

        $livewireComponents = [];

        foreach ($graph->nodesOfType(NodeType::LivewireComponent) as $node) {
            $properties = $node->metadata['public_properties'] ?? [];
            $methods = $node->metadata['public_methods'] ?? [];
            $modelIds = $node->metadata['model_ids'] ?? [];

            $livewireComponents[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'alias' => $node->metadata['alias'] ?? '',
                'view' => $node->metadata['view'] ?? null,
                'models' => is_array($modelIds) ? count($modelIds) : 0,
                'properties' => is_array($properties) ? count($properties) : 0,
                'methods' => is_array($methods) ? count($methods) : 0,
                'used_in_views' => count(array_filter(
                    $graph->incomingEdges($node->id),
                    static fn (Edge $edge): bool => $edge->type === EdgeType::ViewRendersLivewire,
                )),
                'status' => $node->status->value,
            ];
        }

        return [
            'models' => $this->sortRows($models),
            'relations' => $this->sortRows($relations),
            'resources' => $this->sortRows($resources),
            'livewire_components' => $this->sortRows($livewireComponents),
            ...$this->httpTables($graph),
            ...$this->viewTables($graph),
        ];
    }

    /**
     * @return array{views: list<array<string, mixed>>, components: list<array<string, mixed>>, externals: list<array<string, mixed>>}
     */
    protected function viewTables(Graph $graph): array
    {
        $usedBy = static fn (Node $node): int => count(array_filter(
            $graph->incomingEdges($node->id),
            static fn (Edge $edge): bool => ($edge->type === EdgeType::RendersView || $edge->type->isViewReference()),
        ));

        $views = [];
        $components = [];
        $externals = [];

        foreach ($graph->nodesOfType(NodeType::View) as $node) {
            $views[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'file' => $node->metadata['file'] ?? '',
                'kind' => $node->metadata['kind'] ?? 'partial',
                'used_by' => $usedBy($node),
                'uses' => count(array_filter(
                    $graph->outgoingEdges($node->id),
                    static fn (Edge $edge): bool => ($edge->type === EdgeType::RendersView || $edge->type->isViewReference()),
                )),
                'status' => $node->status->value,
            ];

            if (($node->metadata['kind'] ?? null) === 'component') {
                $components[] = [
                    'id' => $node->id->value,
                    'label' => $node->label,
                    'detail' => $node->metadata['file'] ?? '',
                    'type' => 'anonymous_component',
                    'view' => $node->label,
                    'used_by' => $usedBy($node),
                    'status' => $node->status->value,
                ];
            }
        }

        foreach ([NodeType::BladeComponent, NodeType::FilamentComponent] as $type) {
            foreach ($graph->nodesOfType($type) as $node) {
                $rendered = [];

                foreach ($graph->outgoingEdges($node->id) as $edge) {
                    if ($edge->type === EdgeType::RendersView) {
                        $rendered[] = $graph->node($edge->target)->label ?? $edge->target->value;
                    }
                }

                $components[] = [
                    'id' => $node->id->value,
                    'label' => $node->label,
                    'detail' => $node->subtitle ?? '',
                    'type' => $type->value,
                    'view' => implode(', ', $rendered),
                    'used_by' => $usedBy($node),
                    'status' => $node->status->value,
                ];
            }
        }

        foreach ($graph->nodesOfType(NodeType::ExternalView) as $node) {
            $externals[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'package' => $node->metadata['package'] ?? null,
                'missing' => ($node->metadata['missing'] ?? false) === true,
                'used_by' => $usedBy($node),
            ];
        }

        return [
            'views' => $this->sortRows($views),
            'components' => $this->sortRows($components),
            'externals' => $this->sortRows($externals),
        ];
    }

    /**
     * @return array{routes: list<array<string, mixed>>, events: list<array<string, mixed>>, dispatches: list<array<string, mixed>>}
     */
    protected function httpTables(Graph $graph): array
    {
        $routes = [];

        foreach ($graph->nodesOfType(NodeType::Route) as $node) {
            $action = null;
            $formRequests = [];
            $models = [];

            foreach ($this->stringMap($node->metadata['bound_parameters'] ?? []) as $class) {
                $models[] = ClassName::shortName($class);
            }

            foreach ($graph->outgoingEdges($node->id) as $edge) {
                $target = $graph->node($edge->target);

                if ($target === null) {
                    continue;
                }

                if ($edge->type === EdgeType::RouteRendersLivewire) {
                    $action = $target->label;
                }

                if ($edge->type !== EdgeType::RouteHandledByController) {
                    continue;
                }

                $action = $target->label . '@' . $edge->label;
                $actions = $target->metadata['actions'] ?? [];
                $details = is_array($actions) && is_array($actions[$edge->label] ?? null) ? $actions[$edge->label] : [];

                foreach (is_array($details['form_requests'] ?? null) ? $details['form_requests'] : [] as $class) {
                    $formRequests[] = ClassName::shortName((string) $class);
                }

                foreach (array_keys(is_array($details['models'] ?? null) ? $details['models'] : []) as $class) {
                    $models[] = ClassName::shortName((string) $class);
                }
            }

            $actionType = $node->metadata['action_type'] ?? null;
            $models = array_values(array_unique($models));
            sort($models, SORT_STRING);

            $routes[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'uri' => $node->metadata['uri'] ?? '',
                'name' => $node->metadata['name'] ?? null,
                'action' => $action ?? (is_string($actionType) ? ucfirst($actionType) : null),
                'middleware' => implode(', ', array_filter(is_array($node->metadata['middleware'] ?? null) ? $node->metadata['middleware'] : [], 'is_string')),
                'form_requests' => implode(', ', $formRequests),
                'models' => implode(', ', $models),
                'status' => $node->status->value,
            ];
        }

        usort($routes, static fn (array $a, array $b): int => [$a['uri'], $a['label']] <=> [$b['uri'], $b['label']]);

        $events = [];

        foreach ($graph->nodesOfType(NodeType::Event) as $node) {
            $listeners = [];
            $queued = 0;

            foreach ($graph->outgoingEdges($node->id) as $edge) {
                $listener = $edge->type === EdgeType::EventHandledByListener ? $graph->node($edge->target) : null;

                if ($listener !== null) {
                    $listeners[] = $listener->label;
                    $queued += ($listener->metadata['queued'] ?? false) === true ? 1 : 0;
                }
            }

            $events[] = [
                'id' => $node->id->value,
                'label' => $node->label,
                'namespace' => $node->metadata['namespace'] ?? '',
                'listeners' => implode(', ', $listeners),
                'queued_listeners' => $queued,
                'dispatched_by' => implode(', ', $this->dispatcherLabels($graph, $node)),
                'status' => $node->status->value,
            ];
        }

        $dispatches = [];

        foreach ([NodeType::Job, NodeType::Mailable, NodeType::Notification] as $type) {
            foreach ($graph->nodesOfType($type) as $node) {
                $dispatches[] = [
                    'id' => $node->id->value,
                    'label' => $node->label,
                    'namespace' => $node->metadata['namespace'] ?? '',
                    'kind' => $node->type->value,
                    'queued' => ($node->metadata['queued'] ?? false) === true,
                    'dispatched_by' => implode(', ', $this->dispatcherLabels($graph, $node)),
                    'status' => $node->status->value,
                ];
            }
        }

        return [
            'routes' => $routes,
            'events' => $this->sortRows($events),
            'dispatches' => $this->sortRows($dispatches),
        ];
    }

    /**
     * @return list<string>
     */
    protected function dispatcherLabels(Graph $graph, Node $node): array
    {
        $labels = [];

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type === EdgeType::Dispatches) {
                $labels[] = $graph->node($edge->source)->label ?? $edge->source->value;
            }
        }

        $labels = array_values(array_unique($labels));
        sort($labels, SORT_STRING);

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    protected function stringMap(mixed $values): array
    {
        $map = [];

        foreach (is_array($values) ? $values : [] as $key => $value) {
            if (is_string($value)) {
                $map[(string) $key] = $value;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public function getAvailablePanelIds(): array
    {
        try {
            $snapshot = $this->manager()->discover();
        } catch (Throwable) {
            return [];
        }

        $ids = array_map(
            static fn (PanelData $panel): string => $panel->id,
            $snapshot->panels,
        );

        sort($ids, SORT_STRING);

        return $ids;
    }

    public function isLaravelScopeAllowed(): bool
    {
        return (bool) $this->configRepository()->get('filament-dependency-graph.laravel_scope_enabled', true);
    }

    public function isHttpScopeAllowed(): bool
    {
        return (bool) $this->configRepository()->get('filament-dependency-graph.http.enabled', true);
    }

    public function isHttpScope(): bool
    {
        return $this->currentScope() === GraphScope::Http;
    }

    public function isViewsScopeAllowed(): bool
    {
        return (bool) $this->configRepository()->get('filament-dependency-graph.views.enabled', true);
    }

    /**
     * Middleware names used by the routes of the HTTP scope.
     *
     * @return list<string>
     */
    public function getMiddlewareOptions(): array
    {
        if (! $this->isHttpScope()) {
            return [];
        }

        try {
            $graph = $this->searchableGraph();
        } catch (Throwable) {
            return [];
        }

        $names = [];

        foreach ($graph->nodesOfType(NodeType::Route) as $route) {
            // What the developer wrote, even a class name...
            foreach ($this->stringMap($route->metadata['middleware'] ?? []) as $name) {
                $names[explode(':', $name, 2)[0]] = true;
            }

            // ...plus the aliases and group names the resolution adds, but
            // not the resolved class names, which would only add noise.
            foreach ($this->stringMap($route->metadata['resolved_middleware'] ?? []) as $name) {
                if (! str_contains($name, '\\')) {
                    $names[explode(':', $name, 2)[0]] = true;
                }
            }

            unset($names['Closure']);
        }

        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public function getNodeTypeOptions(): array
    {
        $options = [];

        $scope = $this->currentScope();

        foreach (NodeType::cases() as $type) {
            $relevant = match ($scope) {
                GraphScope::Http => $type->isHttp()
                    || in_array($type, [NodeType::Model, NodeType::LivewireComponent, NodeType::View, NodeType::ExternalView], true),
                GraphScope::Views => $type->isView()
                    || in_array($type, [NodeType::LivewireComponent, NodeType::Route, NodeType::Controller, NodeType::Mailable, NodeType::Notification], true),
                default => ! $type->isHttp() && ! $type->isView(),
            };

            if ($relevant) {
                $options[$type->value] = __('filament-dependency-graph::graph.node_types.' . $type->value);
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getRelationTypeOptions(): array
    {
        $options = [];

        foreach (RelationType::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    protected function currentScope(): GraphScope
    {
        $scope = GraphScope::tryFrom($this->scope) ?? GraphScope::Filament;

        return match (true) {
            $scope === GraphScope::Laravel && ! $this->isLaravelScopeAllowed(),
            $scope === GraphScope::Http && ! $this->isHttpScopeAllowed(),
            $scope === GraphScope::Views && ! $this->isViewsScopeAllowed() => GraphScope::Filament,
            default => $scope,
        };
    }

    protected function graphQuery(): GraphQuery
    {
        $scope = $this->currentScope();

        $nodeTypes = [];

        if ($this->hiddenNodeTypes !== []) {
            foreach (NodeType::cases() as $type) {
                if (! in_array($type->value, $this->hiddenNodeTypes, true)) {
                    $nodeTypes[] = $type;
                }
            }
        }

        $relationTypes = [];

        if ($this->hiddenRelationTypes !== []) {
            foreach (RelationType::cases() as $type) {
                if (! in_array($type->value, $this->hiddenRelationTypes, true)) {
                    $relationTypes[] = $type;
                }
            }
        }

        return new GraphQuery(
            scope: $scope,
            panelIds: $this->panelFilter,
            nodeTypes: $nodeTypes,
            relationTypes: $relationTypes,
            focusNodeId: $this->focus,
            depth: $this->depth,
            direction: TraversalDirection::tryFrom($this->direction) ?? TraversalDirection::Both,
            includeOrphans: $this->showOrphans || $this->onlyOrphans,
            middleware: $scope === GraphScope::Http && $this->middlewareFilter !== '' ? $this->middlewareFilter : null,
        );
    }

    protected function currentGraph(): Graph
    {
        if ($this->memoizedGraph instanceof Graph) {
            return $this->memoizedGraph;
        }

        $graph = $this->manager()->graph($this->graphQuery());

        return $this->memoizedGraph = $this->applyLocalFilters($graph);
    }

    /**
     * Graph used by search: same scope and panels, but no focus and no
     * transient filters, so every reachable node stays findable.
     */
    protected function searchableGraph(): Graph
    {
        if ($this->memoizedSearchGraph instanceof Graph) {
            return $this->memoizedSearchGraph;
        }

        return $this->memoizedSearchGraph = $this->manager()->graph(new GraphQuery(
            scope: $this->currentScope(),
            panelIds: $this->panelFilter,
        ));
    }

    protected function applyLocalFilters(Graph $graph): Graph
    {
        $needsFilter = $this->onlyOrphans
            || $this->onlyCycles
            || $this->onlyWithoutResource
            || $this->namespaceFilter !== ''
            || $this->ownershipFilter !== 'all';

        if (! $needsFilter) {
            return $graph;
        }

        $keep = [];

        foreach ($graph->nodes as $node) {
            if ($this->passesLocalFilters($node)) {
                $keep[] = $node->id->value;
            }
        }

        return $graph->subgraph($keep);
    }

    protected function passesLocalFilters(Node $node): bool
    {
        if ($node->type !== NodeType::Model) {
            return ! ($this->onlyOrphans || $this->onlyCycles || $this->onlyWithoutResource);
        }

        if ($this->onlyOrphans && ! in_array('Orphan', $node->badges, true)) {
            return false;
        }

        if ($this->onlyCycles && ! in_array('Cycle', $node->badges, true)) {
            return false;
        }

        if ($this->onlyWithoutResource && ! in_array('No Resource', $node->badges, true)) {
            return false;
        }

        if ($this->namespaceFilter !== '') {
            $namespace = $node->metadata['namespace'] ?? '';
            $wanted = SearchNormalizer::normalize($this->namespaceFilter);
            $normalized = is_string($namespace) ? SearchNormalizer::normalize($namespace) : '';

            if ($wanted !== '' && ! str_contains($normalized, $wanted)) {
                return false;
            }
        }

        if ($this->ownershipFilter === 'application' && ($node->metadata['application_owned'] ?? true) !== true) {
            return false;
        }

        if ($this->ownershipFilter === 'vendor' && ($node->metadata['application_owned'] ?? true) === true) {
            return false;
        }

        return true;
    }

    /**
     * Routes grouped by their first URI segment, followed by the events that
     * nothing in the application dispatches.
     *
     * @return list<array<string, mixed>>
     */
    protected function httpTree(Graph $graph, int $maxDepth): array
    {
        $groups = [];

        foreach ($graph->nodesOfType(NodeType::Route) as $route) {
            $uri = $route->metadata['uri'] ?? '';
            $segment = '/' . explode('/', trim(is_string($uri) ? $uri : '', '/'))[0];
            $groups[$segment][] = $route;
        }

        ksort($groups, SORT_STRING);

        $tree = [];

        foreach ($groups as $segment => $routes) {
            usort($routes, static fn (Node $a, Node $b): int => [$a->metadata['uri'] ?? '', $a->label] <=> [$b->metadata['uri'] ?? '', $b->label]);

            $tree[] = $this->treeGroup('group:' . $segment, $segment, $graph, $routes, $maxDepth);
        }

        $undispatched = array_values(array_filter(
            $graph->nodesOfType(NodeType::Event),
            static fn (Node $event): bool => in_array('No dispatcher found', $event->badges, true),
        ));

        if ($undispatched !== []) {
            $tree[] = $this->treeGroup(
                'group:undispatched-events',
                __('filament-dependency-graph::graph.tree.undispatched_events'),
                $graph,
                $undispatched,
                $maxDepth,
            );
        }

        return $tree;
    }

    /**
     * The Views tree starts from the owners rendering views, grouped by
     * kind, and ends with the templates nothing references.
     *
     * @return list<array<string, mixed>>
     */
    protected function viewsTree(Graph $graph, int $maxDepth): array
    {
        $groups = [
            'routes' => [NodeType::Route, NodeType::Controller],
            'livewire' => [NodeType::LivewireComponent],
            'filament' => [NodeType::FilamentComponent],
            'blade_components' => [NodeType::BladeComponent],
            'mail' => [NodeType::Mailable, NodeType::Notification],
        ];

        $tree = [];
        // Layouts and partials are shared by many pages: each node is
        // unfolded once for the whole tree, later occurrences are marked.
        $visited = [];

        foreach ($groups as $group => $types) {
            $owners = [];

            foreach ($types as $type) {
                foreach ($graph->nodesOfType($type) as $node) {
                    foreach ($graph->outgoingEdges($node->id) as $edge) {
                        if ($edge->type === EdgeType::RendersView) {
                            $owners[] = $node;

                            break;
                        }
                    }
                }
            }

            if ($owners !== []) {
                usort($owners, static fn (Node $a, Node $b): int => strcmp($a->label, $b->label));
                $tree[] = $this->treeGroup('group:' . $group, __('filament-dependency-graph::graph.tree.groups.' . $group), $graph, $owners, $maxDepth, $this->viewsTreeFilter(), $visited);
            }
        }

        $unreferenced = array_values(array_filter(
            $graph->nodesOfType(NodeType::View),
            static fn (Node $view): bool => in_array('No reference found', $view->badges, true),
        ));

        if ($unreferenced !== []) {
            $tree[] = $this->treeGroup(
                'group:unreferenced-views',
                __('filament-dependency-graph::graph.tree.unreferenced_views'),
                $graph,
                $unreferenced,
                $maxDepth,
                $this->viewsTreeFilter(),
                $visited,
            );
        }

        return $tree;
    }

    protected function viewsTreeFilter(): Closure
    {
        return static fn (Edge $edge): bool => ($edge->type === EdgeType::RendersView || $edge->type->isViewReference());
    }

    /**
     * The HTTP tree reads like one request: model relations are left to the
     * other scopes, and below a controller only the edges of the action the
     * route calls are followed.
     */
    protected function httpTreeFilter(Graph $graph, Node $root): Closure
    {
        $action = null;

        foreach ($graph->outgoingEdges($root->id) as $edge) {
            if ($edge->type === EdgeType::RouteHandledByController) {
                $action = $edge->label;
            }
        }

        return static function (Edge $edge) use ($graph, $action): bool|string {
            // Views are leaves of the HTTP map.
            if ($edge->type === EdgeType::ModelRelation || $edge->type->isViewReference()) {
                return false;
            }

            if ($action === null || $graph->node($edge->source)?->type !== NodeType::Controller) {
                return true;
            }

            $methods = $edge->metadata['methods'] ?? null;

            if (is_array($methods) && ! in_array($action, $methods, true)) {
                return false;
            }

            // Requests and models are labelled with every action using
            // them; under one route, only that route's action matters.
            return in_array($edge->type, [EdgeType::ControllerValidatesWith, EdgeType::ControllerUsesModel], true)
                ? $action
                : true;
        };
    }

    /**
     * @param  list<Node>  $nodes
     * @param  (Closure(Edge): (bool|string))|null  $follow  Defaults to the HTTP rules of each root.
     * @param  array<string, true>|null  $visited  Shared across roots when given; each root still unfolds.
     * @return array<string, mixed>
     */
    protected function treeGroup(string $id, string $label, Graph $graph, array $nodes, int $maxDepth, ?Closure $follow = null, ?array &$visited = null): array
    {
        $children = [];

        foreach ($nodes as $node) {
            $rootId = $node->id->value;
            $filter = $follow ?? $this->httpTreeFilter($graph, $node);

            if ($visited === null) {
                $rootVisited = [];
                $children[] = $this->treeNode($graph, $rootId, null, $maxDepth + 1, $rootVisited, $filter);

                continue;
            }

            unset($visited[$rootId]);
            $children[] = $this->treeNode($graph, $rootId, null, $maxDepth + 1, $visited, $filter);
        }

        return [
            'id' => $id,
            'label' => $label,
            'type' => 'group',
            'relation' => null,
            'already_shown' => false,
            'children' => array_values(array_filter($children)),
        ];
    }

    /**
     * @param  array<string, true>  $visited
     * @param  (Closure(Edge): (bool|string))|null  $follow  False skips an edge, a string replaces its branch label.
     * @return array<string, mixed>|null
     */
    protected function treeNode(
        Graph $graph,
        string $nodeId,
        ?string $viaRelation,
        int $remainingDepth,
        array &$visited,
        ?Closure $follow = null,
    ): ?array {
        $node = $graph->node($nodeId);

        if ($node === null) {
            return null;
        }

        $alreadyShown = isset($visited[$nodeId]);

        $item = [
            'id' => $nodeId,
            'label' => $node->label,
            'type' => $node->type->value,
            'relation' => $viaRelation,
            'already_shown' => $alreadyShown,
            'children' => [],
        ];

        if ($alreadyShown || $remainingDepth <= 1) {
            return $item;
        }

        $visited[$nodeId] = true;

        $children = [];

        foreach ($graph->outgoingEdges($nodeId) as $edge) {
            $decision = $follow === null ? true : $follow($edge);

            if ($decision === false) {
                continue;
            }

            $relation = is_string($decision) ? $decision : $edge->label;
            $childLabel = $graph->node($edge->target)->label ?? '';

            $children[] = [
                'sort' => [$relation, $childLabel, $edge->type->value],
                'target' => $edge->target->value,
                'relation' => $relation,
            ];
        }

        usort($children, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        foreach ($children as $child) {
            $childNode = $this->treeNode($graph, $child['target'], $child['relation'], $remainingDepth - 1, $visited, $follow);

            if ($childNode !== null) {
                $item['children'][] = $childNode;
            }
        }

        return $item;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function sortRows(array $rows): array
    {
        usort(
            $rows,
            static fn (array $a, array $b): int => [
                SearchNormalizer::normalize((string) ($a['label'] ?? '')),
                (string) ($a['id'] ?? ''),
            ] <=> [
                SearchNormalizer::normalize((string) ($b['label'] ?? '')),
                (string) ($b['id'] ?? ''),
            ],
        );

        return $rows;
    }

    protected function forgetMemoizedGraphs(): void
    {
        $this->memoizedGraph = null;
        $this->memoizedSearchGraph = null;
    }

    protected function manager(): DependencyGraphManager
    {
        return app(DependencyGraphManager::class);
    }

    protected function configRepository(): Repository
    {
        return app(Repository::class);
    }
}
