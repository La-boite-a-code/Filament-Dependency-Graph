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
        <x-filament::section compact>
            <div class="fdg-toolbar">
                <div class="fdg-toolbar-group">
                    <label class="fdg-label" for="fdg-scope">{{ __('filament-dependency-graph::graph.toolbar.scope') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="fdg-scope" wire:model.live="scope">
                            <option value="filament">{{ __('filament-dependency-graph::graph.toolbar.scope_filament') }}</option>
                            @if ($this->isLaravelScopeAllowed())
                                <option value="laravel">{{ __('filament-dependency-graph::graph.toolbar.scope_laravel') }}</option>
                            @endif
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <x-filament::tabs :label="__('filament-dependency-graph::graph.title')">
                    @foreach (['graph', 'tree', 'table'] as $mode)
                        <x-filament::tabs.item
                            :active="$this->activeView === $mode"
                            wire:click="setView('{{ $mode }}')"
                        >
                            {{ __('filament-dependency-graph::graph.toolbar.view_' . $mode) }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>

                <div class="fdg-toolbar-group fdg-search-wrapper">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="fdg-block">
                        <x-filament::input
                            id="fdg-search"
                            type="search"
                            placeholder="{{ __('filament-dependency-graph::graph.toolbar.search_placeholder') }}"
                            autocomplete="off"
                            wire:model.live.debounce.300ms="search"
                        />
                    </x-filament::input.wrapper>

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
                    <x-filament::button color="gray" size="sm" wire:click="export('json')">
                        {{ __('filament-dependency-graph::graph.toolbar.export_json') }}
                    </x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="export('mermaid')">
                        {{ __('filament-dependency-graph::graph.toolbar.export_mermaid') }}
                    </x-filament::button>
                    <x-filament::button color="danger" size="sm" outlined wire:click="resetGraph">
                        {{ __('filament-dependency-graph::graph.toolbar.reset') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Layout: explorer / workspace / inspector --}}
        <div class="fdg-layout {{ $inspection !== null ? 'fdg-has-inspector' : '' }}">
            <x-filament::section compact class="fdg-explorer" aria-label="{{ __('filament-dependency-graph::graph.explorer.title') }}">
                @include('filament-dependency-graph::partials.explorer')
            </x-filament::section>

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
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="fdg-layout" wire:model.live="graphLayout">
                                <option value="hierarchical">{{ __('filament-dependency-graph::graph.workspace.layout_hierarchical') }}</option>
                                <option value="force">{{ __('filament-dependency-graph::graph.workspace.layout_force') }}</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
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
                                <x-filament::button color="gray" size="sm" x-on:click="fit()">
                                    {{ __('filament-dependency-graph::graph.workspace.fit') }}
                                </x-filament::button>
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
                <x-filament::section compact class="fdg-inspector" aria-label="{{ __('filament-dependency-graph::graph.inspector.title') }}">
                    @include('filament-dependency-graph::partials.inspector', ['inspection' => $inspection])
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
