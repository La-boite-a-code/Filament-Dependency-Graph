@php
    $subjectPresentation = match ($inspection['subject_type']) {
        'panel' => [
            'label' => __('filament-dependency-graph::graph.inspector.types.panel'),
            'color' => 'gray',
            'icon' => 'heroicon-m-squares-2x2',
        ],
        'resource' => [
            'label' => __('filament-dependency-graph::graph.inspector.types.resource'),
            'color' => 'primary',
            'icon' => 'heroicon-m-rectangle-stack',
        ],
        'livewire_component' => [
            'label' => __('filament-dependency-graph::graph.inspector.types.livewire_component'),
            'color' => 'info',
            'icon' => 'heroicon-m-bolt',
        ],
        'model' => [
            'label' => __('filament-dependency-graph::graph.inspector.types.model'),
            'color' => 'success',
            'icon' => 'heroicon-m-circle-stack',
        ],
        'polymorphic_target' => [
            'label' => __('filament-dependency-graph::graph.inspector.types.polymorphic_target'),
            'color' => 'warning',
            'icon' => 'heroicon-m-puzzle-piece',
        ],
        'edge' => [
            'label' => __('filament-dependency-graph::graph.inspector.types.edge'),
            'color' => 'warning',
            'icon' => 'heroicon-m-arrows-right-left',
        ],
        default => [
            'label' => str($inspection['subject_type'])->replace('_', ' ')->headline()->toString(),
            'color' => 'gray',
            'icon' => 'heroicon-m-cube',
        ],
    };

    $sectionIcons = [
        'identity' => 'heroicon-m-identification',
        'filament' => 'heroicon-m-rectangle-stack',
        'livewire' => 'heroicon-m-bolt',
        'relationships' => 'heroicon-m-arrows-right-left',
        'database' => 'heroicon-m-circle-stack',
        'behavior' => 'heroicon-m-code-bracket-square',
        'diagnostics' => 'heroicon-m-shield-check',
        'endpoints' => 'heroicon-m-arrow-long-right',
        'relation' => 'heroicon-m-link',
        'keys' => 'heroicon-m-key',
        'rendering' => 'heroicon-m-eye',
        'public_api' => 'heroicon-m-command-line',
        'models' => 'heroicon-m-circle-stack',
        'labels' => 'heroicon-m-tag',
        'navigation' => 'heroicon-m-map',
        'pages' => 'heroicon-m-document-magnifying-glass',
        'relation_managers' => 'heroicon-m-user-group',
        'resources' => 'heroicon-m-rectangle-stack',
    ];

    $codeLabels = [
        'Alias',
        'Class',
        'Component',
        'Connection',
        'File',
        'Foreign key',
        'Icon',
        'Key type',
        'Local key',
        'Method',
        'Model',
        'Morph type',
        'Namespace',
        'Owner key',
        'Pivot table',
        'Primary key',
        'Table',
        'View',
    ];

    $badgeListLabels = [
        'Badges',
        'Components',
        'Fillable',
        'Guarded',
        'Hidden',
        'Methods',
        'Panels',
        'Properties',
        'References',
        'Resources',
        'Traits',
        'Visible',
    ];

    $listIcons = [
        'Components' => 'heroicon-m-bolt',
        'Incoming' => 'heroicon-m-arrow-down-left',
        'Models' => 'heroicon-m-circle-stack',
        'Outgoing' => 'heroicon-m-arrow-up-right',
        'Pages' => 'heroicon-m-document-text',
        'Relation managers' => 'heroicon-m-user-group',
        'Resources' => 'heroicon-m-rectangle-stack',
        'Warnings' => 'heroicon-m-exclamation-triangle',
    ];
@endphp

<div class="fdg-inspector-inner">
    <div class="fdg-inspector-summary">
        <x-filament::badge
            :color="$subjectPresentation['color']"
            :icon="$subjectPresentation['icon']"
            size="sm"
            class="fdg-inspector-type"
        >
            {{ $subjectPresentation['label'] }}
        </x-filament::badge>

        @if ($inspection['subject_type'] !== 'edge')
            <x-filament::button
                color="primary"
                icon="heroicon-m-viewfinder-circle"
                outlined
                size="sm"
                wire:click="focusOnNode('{{ $inspection['subject_id'] }}')"
            >
                {{ __('filament-dependency-graph::graph.inspector.focus_node') }}
            </x-filament::button>
        @endif
    </div>

    <div class="fdg-inspector-sections">
        @foreach ($inspection['sections'] as $section)
            <x-filament::section
                compact
                :heading="$section['title']"
                :icon="$sectionIcons[$section['key']] ?? 'heroicon-m-information-circle'"
                icon-color="gray"
                icon-size="sm"
                class="fdg-inspector-section"
            >
                <dl class="fdg-inspector-entries">
                    @foreach ($section['entries'] as $label => $value)
                        @php
                            $normalizedValue = is_bool($value)
                                ? ($value ? 'yes' : 'no')
                                : (is_scalar($value) ? strtolower((string) $value) : null);
                            $isStatus = $label === 'Status';
                            $isBoolean = is_string($normalizedValue)
                                && in_array($normalizedValue, ['yes', 'no', 'unknown'], true);
                            $isCode = in_array($label, $codeLabels, true);
                        @endphp

                        <div class="fdg-inspector-entry">
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if (is_array($value))
                                    @if ($value === [])
                                        <div @class([
                                            'fdg-inspector-empty',
                                            'fdg-inspector-empty-success' => $label === 'Warnings',
                                        ])>
                                            <x-filament::icon
                                                :icon="$label === 'Warnings' ? 'heroicon-m-check-circle' : 'heroicon-m-inbox'"
                                                class="fdg-inspector-empty-icon"
                                            />
                                            <span>
                                                {{ $label === 'Warnings'
                                                    ? __('filament-dependency-graph::graph.inspector.no_warnings')
                                                    : __('filament-dependency-graph::graph.inspector.empty_section') }}
                                            </span>
                                        </div>
                                    @elseif (in_array($label, $badgeListLabels, true))
                                        <div class="fdg-inspector-badges">
                                            @foreach ($value as $item)
                                                @php
                                                    $itemLabel = is_bool($item)
                                                        ? __('filament-dependency-graph::graph.table.' . ($item ? 'yes' : 'no'))
                                                        : (string) $item;
                                                @endphp

                                                <x-filament::badge color="gray" size="sm">
                                                    {{ $itemLabel }}
                                                </x-filament::badge>
                                            @endforeach
                                        </div>
                                    @else
                                        <ul class="fdg-inspector-list">
                                            @foreach ($value as $item)
                                                @php
                                                    $itemLabel = is_bool($item)
                                                        ? __('filament-dependency-graph::graph.table.' . ($item ? 'yes' : 'no'))
                                                        : (string) $item;
                                                @endphp

                                                <li>
                                                    <x-filament::icon
                                                        :icon="$listIcons[$label] ?? 'heroicon-m-chevron-right'"
                                                        class="fdg-inspector-list-icon"
                                                    />
                                                    <span>{{ $itemLabel }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @elseif ($value === null || $value === '')
                                    <span class="fdg-inspector-placeholder">—</span>
                                @elseif ($isStatus)
                                    @php
                                        $statusColor = match ($normalizedValue) {
                                            'complete' => 'success',
                                            'partial' => 'warning',
                                            'failed' => 'danger',
                                            default => 'gray',
                                        };
                                        $statusIcon = match ($normalizedValue) {
                                            'complete' => 'heroicon-m-check-circle',
                                            'partial' => 'heroicon-m-exclamation-circle',
                                            'failed' => 'heroicon-m-x-circle',
                                            default => 'heroicon-m-question-mark-circle',
                                        };
                                        $statusTranslation = __('filament-dependency-graph::graph.table.statuses.' . $normalizedValue);
                                    @endphp

                                    <x-filament::badge
                                        :color="$statusColor"
                                        :icon="$statusIcon"
                                        size="sm"
                                    >
                                        {{ str_starts_with($statusTranslation, 'filament-dependency-graph::')
                                            ? $value
                                            : $statusTranslation }}
                                    </x-filament::badge>
                                @elseif ($isBoolean)
                                    @php
                                        $booleanColor = match ($normalizedValue) {
                                            'yes' => 'success',
                                            'no' => 'danger',
                                            default => 'warning',
                                        };
                                        $booleanIcon = match ($normalizedValue) {
                                            'yes' => 'heroicon-m-check',
                                            'no' => 'heroicon-m-x-mark',
                                            default => 'heroicon-m-question-mark-circle',
                                        };
                                    @endphp

                                    <x-filament::badge
                                        :color="$booleanColor"
                                        :icon="$booleanIcon"
                                        size="sm"
                                    >
                                        {{ __('filament-dependency-graph::graph.table.' . $normalizedValue) }}
                                    </x-filament::badge>
                                @elseif ($isCode)
                                    <div class="fdg-inspector-code">
                                        {{ \Filament\Schemas\Components\Text::make((string) $value)
                                            ->copyable()
                                            ->copyMessage(__('filament-dependency-graph::graph.inspector.copied'))
                                            ->copyMessageDuration(1800)
                                            ->tooltip(__('filament-dependency-graph::graph.inspector.copy_value'))
                                            ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                                            ->color('neutral')
                                            ->extraAttributes(['class' => 'fdg-inspector-copyable']) }}
                                    </div>
                                @elseif (is_numeric($value))
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $value }}
                                    </x-filament::badge>
                                @else
                                    <span class="fdg-inspector-value">{{ $value }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endforeach
    </div>
</div>
