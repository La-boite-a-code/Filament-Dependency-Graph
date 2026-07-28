<x-filament-panels::page>
    @php
        $payload = $this->getGraphPayload();
        $inspection = $this->getInspection();
        $searchGroups = $this->getSearchResults();
        $workspaceTitle = __('filament-dependency-graph::graph.workspace.' . $this->activeView . '_title');
    @endphp

    <div
        class="fdg-page"
        x-data="{
            graphExpanded: false,
            toggleGraphExpanded() {
                this.graphExpanded = ! this.graphExpanded;
                this.$nextTick(() => requestAnimationFrame(() => {
                    window.dispatchEvent(new CustomEvent('dependency-graph-fit'));
                }));
            },
        }"
        x-bind:class="{ 'fdg-page-graph-expanded': graphExpanded }"
        x-on:keydown.window="
            if ($event.target.matches('input, textarea, select, [contenteditable]')) return;
            if ($event.key === '/') { $event.preventDefault(); document.getElementById('fdg-search')?.focus(); }
            else if ($event.key === 'Escape' && graphExpanded) { toggleGraphExpanded(); }
            else if ($event.key === 'f' || $event.key === 'F') { $wire.focusOnNode(); }
            else if ($event.key === 'r' || $event.key === 'R') { $wire.resetGraph(); }
            else if ($event.key === 'g' || $event.key === 'G') { $wire.setView('graph'); }
            else if ($event.key === 't' || $event.key === 'T') { $wire.setView('tree'); }
            else if ($event.key === 'l' || $event.key === 'L') { $wire.setView('table'); }
            else if ($event.key === 'e' || $event.key === 'E') { $wire.export('json'); }
        "
    >
        {{-- Toolbar --}}
        <x-filament::section compact class="fdg-command-bar">
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
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-down-tray" wire:click="export('json')">
                        {{ __('filament-dependency-graph::graph.toolbar.export_json') }}
                    </x-filament::button>
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-code-bracket" wire:click="export('mermaid')">
                        {{ __('filament-dependency-graph::graph.toolbar.export_mermaid') }}
                    </x-filament::button>
                    <x-filament::button color="danger" size="sm" outlined icon="heroicon-m-arrow-path" wire:click="resetGraph">
                        {{ __('filament-dependency-graph::graph.toolbar.reset') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Layout: explorer / workspace --}}
        <div
            class="fdg-layout fdg-view-{{ $this->activeView }}"
            x-bind:class="{ 'fdg-layout-expanded': graphExpanded }"
        >
            <x-filament::section
                class="fdg-explorer"
                :heading="__('filament-dependency-graph::graph.explorer.title')"
                :description="__('filament-dependency-graph::graph.explorer.description')"
                aria-label="{{ __('filament-dependency-graph::graph.explorer.title') }}"
            >
                @include('filament-dependency-graph::partials.explorer')
            </x-filament::section>

            <main class="fdg-workspace">
                @if ($payload['error'] !== null)
                    <div class="fdg-error" role="alert">
                        {{ __('filament-dependency-graph::graph.workspace.error') }}
                        {{ $payload['error'] }}
                    </div>
                @endif

                <header class="fdg-workspace-header">
                    <div class="fdg-workspace-heading">
                        <h2 class="fdg-workspace-title">{{ $workspaceTitle }}</h2>
                        <div class="fdg-workspace-meta">
                            <span>
                                {{ __('filament-dependency-graph::graph.workspace.stats', [
                                    'nodes' => $payload['stats']['nodes'],
                                    'edges' => $payload['stats']['edges'],
                                ]) }}
                            </span>
                            @if ($this->activeView === 'graph')
                                <span aria-hidden="true">•</span>
                                <span>{{ __('filament-dependency-graph::graph.workspace.layout_' . $this->graphLayout) }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($this->activeView === 'graph')
                        <div class="fdg-workspace-actions">
                            <label class="fdg-label" for="fdg-layout">{{ __('filament-dependency-graph::graph.workspace.layout') }}</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="fdg-layout" wire:model.live="graphLayout">
                                    <option value="hierarchical">{{ __('filament-dependency-graph::graph.workspace.layout_hierarchical') }}</option>
                                    <option value="force">{{ __('filament-dependency-graph::graph.workspace.layout_force') }}</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    @endif
                </header>

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
                            <div
                                class="fdg-graph-controls"
                                role="toolbar"
                                aria-label="{{ __('filament-dependency-graph::graph.workspace.controls') }}"
                            >
                                <x-filament::icon-button
                                    icon="heroicon-m-minus"
                                    color="gray"
                                    size="sm"
                                    x-on:click="zoomOut()"
                                    :label="__('filament-dependency-graph::graph.workspace.zoom_out')"
                                    :tooltip="__('filament-dependency-graph::graph.workspace.zoom_out')"
                                />
                                <x-filament::icon-button
                                    icon="heroicon-m-plus"
                                    color="gray"
                                    size="sm"
                                    x-on:click="zoomIn()"
                                    :label="__('filament-dependency-graph::graph.workspace.zoom_in')"
                                    :tooltip="__('filament-dependency-graph::graph.workspace.zoom_in')"
                                />
                                <span class="fdg-graph-controls-divider" aria-hidden="true"></span>
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    outlined
                                    icon="heroicon-m-viewfinder-circle"
                                    x-on:click="fit()"
                                >
                                    {{ __('filament-dependency-graph::graph.workspace.fit') }}
                                </x-filament::button>
                                <span class="fdg-graph-controls-divider" aria-hidden="true"></span>
                                <x-filament::icon-button
                                    icon="heroicon-m-arrows-pointing-out"
                                    color="gray"
                                    size="sm"
                                    x-show="! graphExpanded"
                                    x-on:click="toggleGraphExpanded()"
                                    :label="__('filament-dependency-graph::graph.workspace.expand')"
                                    :tooltip="__('filament-dependency-graph::graph.workspace.expand')"
                                />
                                <x-filament::icon-button
                                    icon="heroicon-m-arrows-pointing-in"
                                    color="primary"
                                    size="sm"
                                    x-cloak
                                    x-show="graphExpanded"
                                    x-on:click="toggleGraphExpanded()"
                                    :label="__('filament-dependency-graph::graph.workspace.collapse')"
                                    :tooltip="__('filament-dependency-graph::graph.workspace.collapse')"
                                />
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
        </div>

        <x-filament::modal
            id="fdg-inspector"
            slide-over
            sticky-header
            teleport="body"
            width="lg"
            :heading="$inspection['title'] ?? __('filament-dependency-graph::graph.inspector.title')"
            :description="$inspection['subtitle'] ?? null"
            x-on:modal-closed.window="
                if ($event.detail.id === 'fdg-inspector') {
                    $wire.clearSelection()
                }
            "
        >
            @if ($inspection !== null)
                @include('filament-dependency-graph::partials.inspector', ['inspection' => $inspection])
            @endif
        </x-filament::modal>
    </div>
</x-filament-panels::page>
