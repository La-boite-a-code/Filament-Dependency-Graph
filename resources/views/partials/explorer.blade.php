@php
    $panelIds = $this->getAvailablePanelIds();
@endphp

<div class="fdg-explorer-inner">
    @if (count($panelIds) > 1)
        <section class="fdg-explorer-section">
            <h3 class="fdg-explorer-heading">{{ __('filament-dependency-graph::graph.explorer.panels') }}</h3>

            @foreach ($panelIds as $panelId)
                <label class="fdg-checkbox">
                    <x-filament::input.checkbox
                        value="{{ $panelId }}"
                        wire:model.live="panelFilter"
                    />
                    <span>{{ $panelId }}</span>
                </label>
            @endforeach
        </section>
    @endif

    <section class="fdg-explorer-section">
        <h3 class="fdg-explorer-heading">{{ __('filament-dependency-graph::graph.explorer.node_types') }}</h3>

        @foreach ($this->getNodeTypeOptions() as $value => $label)
            <label class="fdg-checkbox">
                <x-filament::input.checkbox
                    :checked="! in_array($value, $this->hiddenNodeTypes, true)"
                    wire:click="toggleNodeType('{{ $value }}')"
                />
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </section>

    <section class="fdg-explorer-section">
        <h3 class="fdg-explorer-heading">{{ __('filament-dependency-graph::graph.explorer.relation_types') }}</h3>

        @foreach ($this->getRelationTypeOptions() as $value => $label)
            <label class="fdg-checkbox">
                <x-filament::input.checkbox
                    :checked="! in_array($value, $this->hiddenRelationTypes, true)"
                    wire:click="toggleRelationType('{{ $value }}')"
                />
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </section>

    <section class="fdg-explorer-section">
        <h3 class="fdg-explorer-heading">{{ __('filament-dependency-graph::graph.explorer.filters') }}</h3>

        <label class="fdg-label" for="fdg-namespace">{{ __('filament-dependency-graph::graph.explorer.namespace') }}</label>
        <x-filament::input.wrapper>
            <x-filament::input
                id="fdg-namespace"
                type="text"
                wire:model.live.debounce.400ms="namespaceFilter"
            />
        </x-filament::input.wrapper>

        <label class="fdg-label" for="fdg-ownership">{{ __('filament-dependency-graph::graph.explorer.ownership') }}</label>
        <x-filament::input.wrapper>
            <x-filament::input.select id="fdg-ownership" wire:model.live="ownershipFilter">
                <option value="all">{{ __('filament-dependency-graph::graph.explorer.ownership_all') }}</option>
                <option value="application">{{ __('filament-dependency-graph::graph.explorer.ownership_application') }}</option>
                <option value="vendor">{{ __('filament-dependency-graph::graph.explorer.ownership_vendor') }}</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <label class="fdg-checkbox">
            <x-filament::input.checkbox wire:model.live="showOrphans" />
            <span>{{ __('filament-dependency-graph::graph.explorer.show_orphans') }}</span>
        </label>

        <label class="fdg-checkbox">
            <x-filament::input.checkbox wire:model.live="onlyOrphans" />
            <span>{{ __('filament-dependency-graph::graph.explorer.only_orphans') }}</span>
        </label>

        <label class="fdg-checkbox">
            <x-filament::input.checkbox wire:model.live="onlyCycles" />
            <span>{{ __('filament-dependency-graph::graph.explorer.only_cycles') }}</span>
        </label>

        <label class="fdg-checkbox">
            <x-filament::input.checkbox wire:model.live="onlyWithoutResource" />
            <span>{{ __('filament-dependency-graph::graph.explorer.only_without_resource') }}</span>
        </label>
    </section>

    @if ($this->focus !== null)
        <section class="fdg-explorer-section">
            <h3 class="fdg-explorer-heading">{{ __('filament-dependency-graph::graph.explorer.focus') }}</h3>

            <p class="fdg-focus-node">{{ $this->focus }}</p>

            <label class="fdg-label" for="fdg-depth">{{ __('filament-dependency-graph::graph.explorer.focus_depth') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="fdg-depth" wire:model.live="depth">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="">{{ __('filament-dependency-graph::graph.explorer.focus_depth_unlimited') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <label class="fdg-label" for="fdg-direction">{{ __('filament-dependency-graph::graph.explorer.focus_direction') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="fdg-direction" wire:model.live="direction">
                    <option value="incoming">{{ __('filament-dependency-graph::graph.explorer.direction_incoming') }}</option>
                    <option value="outgoing">{{ __('filament-dependency-graph::graph.explorer.direction_outgoing') }}</option>
                    <option value="both">{{ __('filament-dependency-graph::graph.explorer.direction_both') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::button color="gray" size="sm" wire:click="clearFocus">
                {{ __('filament-dependency-graph::graph.explorer.exit_focus') }}
            </x-filament::button>
        </section>
    @endif
</div>
