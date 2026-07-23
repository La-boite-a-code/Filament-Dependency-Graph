<x-filament-panels::page>
    @php
        $payload = $this->getGraphPayload();
        $inspection = $this->getInspection();
        $searchGroups = $this->getSearchResults();
    @endphp

    <div
        class="fdg-page"
        x-data="{}"
        x-on:keydown.window="
            if ($event.target.matches('input, textarea, select, [contenteditable]')) return;
            if ($event.key === '/') { $event.preventDefault(); document.getElementById('fdg-search')?.focus(); }
            else if ($event.key === 'Escape') { $wire.clearSelection(); }
            else if ($event.key === 'f' || $event.key === 'F') { $wire.focusOnNode(); }
            else if ($event.key === 'r' || $event.key === 'R') { $wire.resetGraph(); }
            else if ($event.key === 'g' || $event.key === 'G') { $wire.setView('graph'); }
            else if ($event.key === 't' || $event.key === 'T') { $wire.setView('tree'); }
            else if ($event.key === 'l' || $event.key === 'L') { $wire.setView('table'); }
            else if ($event.key === 'e' || $event.key === 'E') { $wire.export('json'); }
        "
    >
        {{-- Toolbar --}}
        <div class="fdg-toolbar">
            <div class="fdg-toolbar-group">
                <label class="fdg-label" for="fdg-scope">{{ __('filament-dependency-graph::graph.toolbar.scope') }}</label>
                <select id="fdg-scope" class="fdg-select" wire:model.live="scope">
                    <option value="filament">{{ __('filament-dependency-graph::graph.toolbar.scope_filament') }}</option>
                    @if ($this->isLaravelScopeAllowed())
                        <option value="laravel">{{ __('filament-dependency-graph::graph.toolbar.scope_laravel') }}</option>
                    @endif
                </select>
            </div>

            <div class="fdg-toolbar-group fdg-view-switcher" role="tablist">
                @foreach (['graph', 'tree', 'table'] as $mode)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $this->activeView === $mode ? 'true' : 'false' }}"
                        class="fdg-view-button {{ $this->activeView === $mode ? 'fdg-active' : '' }}"
                        wire:click="setView('{{ $mode }}')"
                    >
                        {{ __('filament-dependency-graph::graph.toolbar.view_' . $mode) }}
                    </button>
                @endforeach
            </div>

            <div class="fdg-toolbar-group fdg-search-wrapper">
                <input
                    id="fdg-search"
                    type="search"
                    class="fdg-input"
                    placeholder="{{ __('filament-dependency-graph::graph.toolbar.search_placeholder') }}"
                    autocomplete="off"
                    wire:model.live.debounce.300ms="search"
                />

                @if (trim($this->search) !== '')
                    <div class="fdg-search-results" role="listbox">
                        @forelse ($searchGroups as $group)
                            <div class="fdg-search-group">
                                <div class="fdg-search-group-title">
                                    {{ __('filament-dependency-graph::graph.node_types.' . $group['type']) }}
                                </div>
                                @foreach ($group['results'] as $result)
                                    <button
                                        type="button"
                                        role="option"
                                        class="fdg-search-result"
                                        wire:click="selectSearchResult('{{ $result['node_id'] }}')"
                                    >
                                        <span class="fdg-search-result-label">{{ $result['label'] }}</span>
                                        @if ($result['subtitle'])
                                            <span class="fdg-search-result-subtitle">{{ $result['subtitle'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @empty
                            <div class="fdg-search-empty">{{ __('filament-dependency-graph::graph.search.no_results') }}</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="fdg-toolbar-group">
                <button type="button" class="fdg-button" wire:click="export('json')">
                    {{ __('filament-dependency-graph::graph.toolbar.export_json') }}
                </button>
                <button type="button" class="fdg-button" wire:click="export('mermaid')">
                    {{ __('filament-dependency-graph::graph.toolbar.export_mermaid') }}
                </button>
                <button type="button" class="fdg-button fdg-button-danger" wire:click="resetGraph">
                    {{ __('filament-dependency-graph::graph.toolbar.reset') }}
                </button>
            </div>
        </div>

        {{-- Layout: explorer / workspace / inspector --}}
        <div class="fdg-layout {{ $inspection !== null ? 'fdg-has-inspector' : '' }}">
            <aside class="fdg-explorer" aria-label="{{ __('filament-dependency-graph::graph.explorer.title') }}">
                @include('filament-dependency-graph::partials.explorer')
            </aside>

            <main class="fdg-workspace">
                @if ($payload['error'] !== null)
                    <div class="fdg-error" role="alert">
                        {{ __('filament-dependency-graph::graph.workspace.error') }}
                        {{ $payload['error'] }}
                    </div>
                @endif

                <div class="fdg-workspace-meta">
                    <span>
                        {{ __('filament-dependency-graph::graph.workspace.stats', [
                            'nodes' => $payload['stats']['nodes'],
                            'edges' => $payload['stats']['edges'],
                        ]) }}
                    </span>

                    @if ($this->activeView === 'graph')
                        <label class="fdg-label" for="fdg-layout">{{ __('filament-dependency-graph::graph.workspace.layout') }}</label>
                        <select id="fdg-layout" class="fdg-select" wire:model.live="graphLayout">
                            <option value="hierarchical">{{ __('filament-dependency-graph::graph.workspace.layout_hierarchical') }}</option>
                            <option value="force">{{ __('filament-dependency-graph::graph.workspace.layout_force') }}</option>
                        </select>
                    @endif
                </div>

                @if ($this->activeView === 'graph')
                    @if ($payload['stats']['nodes'] === 0)
                        <div class="fdg-empty">{{ __('filament-dependency-graph::graph.workspace.empty') }}</div>
                    @else
                        <div
                            wire:key="fdg-graph-{{ md5(json_encode($payload['graph']) . $this->graphLayout) }}"
                            x-load
                            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('dependency-graph', 'laboiteacode/filament-dependency-graph') }}"
                            x-data="dependencyGraph({
                                graph: @js($payload['graph']),
                                selected: @js($this->selectedNodeId),
                                layout: @js($this->graphLayout),
                            })"
                            class="fdg-graph-canvas"
                            role="application"
                            aria-label="{{ __('filament-dependency-graph::graph.title') }}"
                        >
                            <div class="fdg-graph-controls">
                                <button type="button" class="fdg-button" x-on:click="fit()" aria-label="{{ __('filament-dependency-graph::graph.workspace.fit') }}">
                                    {{ __('filament-dependency-graph::graph.workspace.fit') }}
                                </button>
                            </div>
                            <div class="fdg-graph-container" x-ref="container"></div>
                        </div>
                    @endif
                @elseif ($this->activeView === 'tree')
                    @include('filament-dependency-graph::partials.tree')
                @else
                    @include('filament-dependency-graph::partials.tables')
                @endif
            </main>

            @if ($inspection !== null)
                <aside class="fdg-inspector" aria-label="{{ __('filament-dependency-graph::graph.inspector.title') }}">
                    @include('filament-dependency-graph::partials.inspector', ['inspection' => $inspection])
                </aside>
            @endif
        </div>
    </div>
</x-filament-panels::page>
