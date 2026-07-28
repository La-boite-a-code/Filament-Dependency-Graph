@php
    $datasets = $this->getTableDatasetOptions();
@endphp

<div class="fdg-table-browser">
    <div class="fdg-table-switcher">
        <x-filament::tabs :label="__('filament-dependency-graph::graph.table.dataset_label')">
            @foreach ($datasets as $dataset => $option)
                <x-filament::tabs.item
                    :active="$this->tableDataset === $dataset"
                    :badge="$option['count']"
                    :icon="$option['icon']"
                    wire:click="setTableDataset('{{ $dataset }}')"
                >
                    {{ $option['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    <div class="fdg-native-table">
        {{ $this->table }}
    </div>
</div>
