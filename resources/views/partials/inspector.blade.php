<div class="fdg-inspector-inner">
    <div class="fdg-inspector-header">
        <div>
            <h2 class="fdg-inspector-title">{{ $inspection['title'] }}</h2>
            @if ($inspection['subtitle'])
                <p class="fdg-inspector-subtitle">{{ $inspection['subtitle'] }}</p>
            @endif
        </div>

        <x-filament::icon-button
            icon="heroicon-m-x-mark"
            color="gray"
            size="sm"
            wire:click="clearSelection"
            :label="__('filament-dependency-graph::graph.inspector.close')"
            :tooltip="__('filament-dependency-graph::graph.inspector.close')"
        />
    </div>

    @if ($inspection['subject_type'] !== 'edge')
        <x-filament::button color="gray" size="sm" wire:click="focusOnNode('{{ $inspection['subject_id'] }}')">
            {{ __('filament-dependency-graph::graph.inspector.focus_node') }}
        </x-filament::button>
    @endif

    @foreach ($inspection['sections'] as $section)
        <section class="fdg-inspector-section">
            <h3 class="fdg-inspector-section-title">{{ $section['title'] }}</h3>

            <dl class="fdg-inspector-entries">
                @foreach ($section['entries'] as $label => $value)
                    <div class="fdg-inspector-entry">
                        <dt>{{ $label }}</dt>
                        <dd>
                            @if (is_array($value))
                                @if ($value === [])
                                    <span class="fdg-muted">{{ __('filament-dependency-graph::graph.inspector.empty_section') }}</span>
                                @else
                                    <ul class="fdg-inspector-list">
                                        @foreach ($value as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @elseif ($value === null || $value === '')
                                <span class="fdg-muted">-</span>
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
