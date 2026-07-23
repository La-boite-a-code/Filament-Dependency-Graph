<div class="fdg-tree-node" role="treeitem">
    <div class="fdg-tree-row">
        @if ($item['relation'] !== null)
            <span class="fdg-tree-relation">{{ $item['relation'] }}</span>
            <span aria-hidden="true">&rarr;</span>
        @endif

        <button
            type="button"
            class="fdg-tree-label fdg-tree-{{ $item['type'] }}"
            wire:click="selectNode('{{ $item['id'] }}')"
        >
            {{ $item['label'] }}
        </button>

        <x-filament::badge color="gray" size="sm" class="fdg-tree-type">
            {{ __('filament-dependency-graph::graph.node_types.' . $item['type']) }}
        </x-filament::badge>

        @if ($item['already_shown'])
            <span class="fdg-muted">[{{ __('filament-dependency-graph::graph.tree.already_shown') }}]</span>
        @endif
    </div>

    @if ($item['children'] !== [])
        <div class="fdg-tree-children" role="group">
            @foreach ($item['children'] as $child)
                @include('filament-dependency-graph::partials.tree-node', ['item' => $child])
            @endforeach
        </div>
    @endif
</div>
