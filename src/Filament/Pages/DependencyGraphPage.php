<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Filament\Pages;

use BackedEnum;
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
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Inspection\DefaultNodeInspector;
use LaBoiteACode\DependencyGraph\Inspection\EdgeInspector;
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

    protected ?Graph $memoizedGraph = null;

    protected ?Graph $memoizedSearchGraph = null;

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

        $this->direction = TraversalDirection::tryFrom($this->direction)->value
            ?? (string) $config->get('filament-dependency-graph.graph.default_direction', 'both');

        $this->showOrphans = (bool) $config->get('filament-dependency-graph.graph.show_orphans', true);
        $this->graphLayout = (string) $config->get('filament-dependency-graph.graph.default_layout', 'hierarchical');
        $this->activeView = in_array($this->activeView, ['graph', 'tree', 'table'], true)
            ? $this->activeView
            : 'graph';
        $this->tableDataset = in_array($this->tableDataset, self::TABLE_DATASETS, true)
            ? $this->tableDataset
            : 'models';

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

    public function setTableDataset(string $dataset): void
    {
        if (! in_array($dataset, self::TABLE_DATASETS, true)) {
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
        $this->tableDataset = 'models';
        $this->tableSearch = '';
        $this->tableSort = null;

        $config = $this->configRepository();

        $scope = $config->get('filament-dependency-graph.default_scope', GraphScope::Filament);
        $this->scope = $scope instanceof GraphScope ? $scope->value : (string) $scope;
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
                'graph' => $graph->toArray(),
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

        $maxDepth = $this->depth ?? (int) $this->configRepository()->get('filament-dependency-graph.graph.default_depth', 2);
        $maxDepth = max($maxDepth, 1);

        $tree = [];

        foreach ($roots as $rootId) {
            $visited = [];
            $tree[] = $this->treeNode($graph, $rootId, null, $maxDepth + 1, $visited);
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

        return [
            'models' => [
                'label' => __('filament-dependency-graph::graph.table.models'),
                'icon' => 'heroicon-m-circle-stack',
                'count' => count($tables['models']),
            ],
            'livewire_components' => [
                'label' => __('filament-dependency-graph::graph.table.livewire_components'),
                'icon' => 'heroicon-m-bolt',
                'count' => count($tables['livewire_components']),
            ],
            'relations' => [
                'label' => __('filament-dependency-graph::graph.table.relations'),
                'icon' => 'heroicon-m-arrows-right-left',
                'count' => count($tables['relations']),
            ],
            'resources' => [
                'label' => __('filament-dependency-graph::graph.table.resources'),
                'icon' => 'heroicon-m-rectangle-stack',
                'count' => count($tables['resources']),
            ],
        ];
    }

    /**
     * @return array<int, IconColumn|TextColumn>
     */
    protected function getDatasetTableColumns(): array
    {
        return match ($this->tableDataset) {
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
     * @return array{models: list<array<string, mixed>>, relations: list<array<string, mixed>>, resources: list<array<string, mixed>>, livewire_components: list<array<string, mixed>>}
     */
    public function getTables(): array
    {
        try {
            $graph = $this->currentGraph();
        } catch (Throwable) {
            return ['models' => [], 'relations' => [], 'resources' => [], 'livewire_components' => []];
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
                'status' => $node->status->value,
            ];
        }

        return [
            'models' => $this->sortRows($models),
            'relations' => $this->sortRows($relations),
            'resources' => $this->sortRows($resources),
            'livewire_components' => $this->sortRows($livewireComponents),
        ];
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

    /**
     * @return array<string, string>
     */
    public function getNodeTypeOptions(): array
    {
        $options = [];

        foreach (NodeType::cases() as $type) {
            $options[$type->value] = __('filament-dependency-graph::graph.node_types.' . $type->value);
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

    protected function graphQuery(): GraphQuery
    {
        $scope = GraphScope::tryFrom($this->scope) ?? GraphScope::Filament;

        if ($scope === GraphScope::Laravel && ! $this->isLaravelScopeAllowed()) {
            $scope = GraphScope::Filament;
        }

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

        $scope = GraphScope::tryFrom($this->scope) ?? GraphScope::Filament;

        return $this->memoizedSearchGraph = $this->manager()->graph(new GraphQuery(
            scope: $scope,
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
     * @param  array<string, true>  $visited
     * @return array<string, mixed>|null
     */
    protected function treeNode(Graph $graph, string $nodeId, ?string $viaRelation, int $remainingDepth, array &$visited): ?array
    {
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
            $childLabel = $graph->node($edge->target)->label ?? '';

            $children[] = [
                'sort' => [$edge->label, $childLabel, $edge->type->value],
                'target' => $edge->target->value,
                'relation' => $edge->label,
            ];
        }

        usort($children, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        foreach ($children as $child) {
            $childNode = $this->treeNode($graph, $child['target'], $child['relation'], $remainingDepth - 1, $visited);

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
