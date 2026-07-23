@php
    $tables = $this->getTables();
    $t = fn (string $key): string => __('filament-dependency-graph::graph.table.' . $key);
    $bool = fn (?bool $value): string => $value === null
        ? __('filament-dependency-graph::graph.table.unknown')
        : ($value ? __('filament-dependency-graph::graph.table.yes') : __('filament-dependency-graph::graph.table.no'));
@endphp

<div class="fdg-tables">
    <section class="fdg-table-section">
        <h3 class="fdg-table-title">{{ $t('models') }}</h3>

        <div class="fdg-table-scroll">
            <table class="fdg-table">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortTableBy('label')">{{ $t('model') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('namespace')">{{ $t('namespace') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('table')">{{ $t('database_table') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('resources')">{{ $t('resources_count') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('outgoing')">{{ $t('outgoing') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('incoming')">{{ $t('incoming') }}</button></th>
                        <th>{{ $t('soft_deletes') }}</th>
                        <th>{{ $t('status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tables['models'] as $row)
                        <tr wire:click="selectNode('{{ $row['id'] }}')" class="fdg-table-row">
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['namespace'] }}</td>
                            <td>{{ $row['table'] }}</td>
                            <td>{{ $row['resources'] }}</td>
                            <td>{{ $row['outgoing'] }}</td>
                            <td>{{ $row['incoming'] }}</td>
                            <td>{{ $bool($row['soft_deletes']) }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="fdg-table-section">
        <h3 class="fdg-table-title">{{ $t('relations') }}</h3>

        <div class="fdg-table-scroll">
            <table class="fdg-table">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortTableBy('label')">{{ $t('source') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('method')">{{ $t('method') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('type')">{{ $t('type') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('target')">{{ $t('target') }}</button></th>
                        <th>{{ $t('foreign_key') }}</th>
                        <th>{{ $t('pivot') }}</th>
                        <th>{{ $t('nullable') }}</th>
                        <th>{{ $t('status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tables['relations'] as $row)
                        <tr wire:click="selectEdge('{{ $row['id'] }}')" class="fdg-table-row">
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['method'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['target'] }}</td>
                            <td>{{ $row['foreign_key'] ?? '-' }}</td>
                            <td>{{ $row['pivot'] ?? '-' }}</td>
                            <td>{{ $bool($row['nullable']) }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="fdg-table-section">
        <h3 class="fdg-table-title">{{ $t('resources') }}</h3>

        <div class="fdg-table-scroll">
            <table class="fdg-table">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortTableBy('label')">{{ $t('resource') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('model')">{{ $t('model') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('panels')">{{ $t('panels') }}</button></th>
                        <th><button type="button" wire:click="sortTableBy('navigation_group')">{{ $t('navigation_group') }}</button></th>
                        <th>{{ $t('pages') }}</th>
                        <th>{{ $t('relation_managers') }}</th>
                        <th>{{ $t('status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tables['resources'] as $row)
                        <tr wire:click="selectNode('{{ $row['id'] }}')" class="fdg-table-row">
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['model'] }}</td>
                            <td>{{ $row['panels'] }}</td>
                            <td>{{ $row['navigation_group'] ?? '-' }}</td>
                            <td>{{ $row['pages'] }}</td>
                            <td>{{ $row['relation_managers'] }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
