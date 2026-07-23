<div class="fdg-inspector-inner">
    <div class="fdg-inspector-header">
        <div>
            <h2 class="fdg-inspector-title">{{ $inspection['title'] }}</h2>
            @if ($inspection['subtitle'])
                <p class="fdg-inspector-subtitle">{{ $inspection['subtitle'] }}</p>
            @endif
        </div>

        <button
            type="button"
            class="fdg-icon-button"
            wire:click="clearSelection"
            aria-label="{{ __('filament-dependency-graph::graph.inspector.close') }}"
            title="{{ __('filament-dependency-graph::graph.inspector.close') }}"
        >
            &times;
        </button>
    </div>

    @if ($inspection['subject_type'] !== 'edge')
        <button type="button" class="fdg-button" wire:click="focusOnNode('{{ $inspection['subject_id'] }}')">
            {{ __('filament-dependency-graph::graph.inspector.focus_node') }}
        </button>
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
