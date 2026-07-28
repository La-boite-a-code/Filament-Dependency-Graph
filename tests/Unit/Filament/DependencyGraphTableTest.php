<?php

declare(strict_types=1);

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Pagination\LengthAwarePaginator;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;

final class TestableDependencyGraphTablePage extends DependencyGraphPage
{
    /**
     * @var array{
     *     models: list<array<string, mixed>>,
     *     relations: list<array<string, mixed>>,
     *     resources: list<array<string, mixed>>,
     *     livewire_components: list<array<string, mixed>>
     * }
     */
    public array $fixtureTables = [
        'models' => [],
        'relations' => [],
        'resources' => [],
        'livewire_components' => [],
    ];

    /**
     * @return array{
     *     models: list<array<string, mixed>>,
     *     relations: list<array<string, mixed>>,
     *     resources: list<array<string, mixed>>,
     *     livewire_components: list<array<string, mixed>>
     * }
     */
    public function getTables(): array
    {
        return $this->fixtureTables;
    }

    /**
     * @return array<int, IconColumn|TextColumn>
     */
    public function exposedDatasetTableColumns(): array
    {
        return $this->getDatasetTableColumns();
    }

    /**
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    public function exposedDatasetTableRecords(
        ?string $search = null,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
        int $page = 1,
        int $recordsPerPage = 25,
    ): LengthAwarePaginator {
        return $this->getDatasetTableRecords(
            search: $search,
            sortColumn: $sortColumn,
            sortDirection: $sortDirection,
            page: $page,
            recordsPerPage: $recordsPerPage,
        );
    }

    public function exposedFormatTableBoolean(?bool $value): string
    {
        return $this->formatTableBoolean($value);
    }

    public function getTablePaginationPageName(): string
    {
        return 'page';
    }
}

it('defines the expected native Filament columns for every dataset', function (): void {
    $page = new TestableDependencyGraphTablePage;

    $expectedColumns = [
        'models' => [
            'label',
            'table',
            'resources',
            'livewire_components',
            'outgoing',
            'incoming',
            'soft_deletes',
            'status',
        ],
        'livewire_components' => [
            'label',
            'view',
            'models',
            'properties',
            'methods',
            'status',
        ],
        'relations' => [
            'label',
            'method',
            'type',
            'target',
            'foreign_key',
            'pivot',
            'nullable',
            'status',
        ],
        'resources' => [
            'label',
            'panels',
            'navigation_group',
            'pages',
            'relation_managers',
            'status',
        ],
    ];

    foreach ($expectedColumns as $dataset => $columns) {
        $page->tableDataset = $dataset;

        expect(
            array_map(
                static fn (IconColumn|TextColumn $column): string => $column->getName(),
                $page->exposedDatasetTableColumns(),
            ),
        )->toBe($columns);
    }
});

it('reports the record count for every table dataset', function (): void {
    $page = new TestableDependencyGraphTablePage;
    $page->fixtureTables = [
        'models' => [
            ['id' => 'model:order'],
            ['id' => 'model:customer'],
        ],
        'relations' => [
            ['id' => 'edge:order:customer'],
        ],
        'resources' => [
            ['id' => 'resource:order'],
            ['id' => 'resource:customer'],
            ['id' => 'resource:invoice'],
        ],
        'livewire_components' => [
            ['id' => 'livewire:orders'],
        ],
    ];

    expect(collect($page->getTableDatasetOptions())->map->count->all())
        ->toBe([
            'models' => 2,
            'livewire_components' => 1,
            'relations' => 1,
            'resources' => 3,
        ]);
});

it('searches table records without case or accent sensitivity', function (): void {
    $page = new TestableDependencyGraphTablePage;
    $page->fixtureTables['models'] = [
        [
            'id' => 'model:order',
            'label' => 'Order',
            'namespace' => 'App\\Domain\\Sales',
        ],
        [
            'id' => 'model:customer',
            'label' => 'ClientÉlite',
            'namespace' => 'App\\Domain\\CRM',
        ],
    ];

    $records = $page->exposedDatasetTableRecords(search: 'CLIENTELITE');

    expect($records->total())->toBe(1)
        ->and($records->getCollection()->keys()->all())->toBe(['model:customer']);
});

it('sorts and paginates custom records while preserving stable record ids', function (): void {
    $page = new TestableDependencyGraphTablePage;
    $page->fixtureTables['models'] = [
        ['id' => 'model:bravo', 'label' => 'Bravo'],
        ['id' => 'model:alpha', 'label' => 'Alpha'],
        ['id' => 'model:charlie', 'label' => 'Charlie'],
    ];

    $firstPage = $page->exposedDatasetTableRecords(
        sortColumn: 'label',
        sortDirection: 'desc',
        recordsPerPage: 2,
    );
    $secondPage = $page->exposedDatasetTableRecords(
        sortColumn: 'label',
        sortDirection: 'desc',
        page: 2,
        recordsPerPage: 2,
    );

    expect($firstPage->total())->toBe(3)
        ->and($firstPage->lastPage())->toBe(2)
        ->and($firstPage->getCollection()->keys()->all())->toBe([
            'model:charlie',
            'model:bravo',
        ])
        ->and($secondPage->getCollection()->keys()->all())->toBe([
            'model:alpha',
        ]);
});

it('formats tri-state table values with the package translations', function (): void {
    $page = new TestableDependencyGraphTablePage;

    expect($page->exposedFormatTableBoolean(true))
        ->toBe(__('filament-dependency-graph::graph.table.yes'))
        ->and($page->exposedFormatTableBoolean(false))
        ->toBe(__('filament-dependency-graph::graph.table.no'))
        ->and($page->exposedFormatTableBoolean(null))
        ->toBe(__('filament-dependency-graph::graph.table.unknown'));
});
