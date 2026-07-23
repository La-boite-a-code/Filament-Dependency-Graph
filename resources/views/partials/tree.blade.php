@php
    $tree = $this->getTree();
@endphp

<div class="fdg-tree" role="tree">
    @forelse ($tree as $item)
        @include('filament-dependency-graph::partials.tree-node', ['item' => $item])
    @empty
        <div class="fdg-empty">{{ __('filament-dependency-graph::graph.tree.empty') }}</div>
    @endforelse
</div>
