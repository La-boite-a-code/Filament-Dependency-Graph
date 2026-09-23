<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection;

use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionSection;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;

/**
 * Inspects edges, most usefully relation edges with their keys, pivot and
 * morph metadata.
 */
final class EdgeInspector
{
    public function inspect(Edge $edge, Graph $graph): InspectionData
    {
        $source = $graph->node($edge->source);
        $target = $graph->node($edge->target);

        $sections = [
            new InspectionSection('endpoints', 'Endpoints', [
                'Source' => $source->label ?? $edge->source->value,
                'Target' => $target->label ?? $edge->target->value,
            ]),
        ];

        if ($edge->type === EdgeType::ModelRelation) {
            $nullable = $edge->metadata['nullable'] ?? null;

            $sections[] = new InspectionSection('relation', 'Relation', [
                'Method' => $this->string($edge, 'method'),
                'Type' => $this->string($edge, 'relation_label'),
                'Polymorphic' => ($edge->metadata['polymorphic'] ?? false) === true ? 'yes' : 'no',
                'Inverse discovered' => ($edge->metadata['inverse_discovered'] ?? false) === true ? 'yes' : 'no',
            ]);

            $sections[] = new InspectionSection('keys', 'Keys', [
                'Foreign key' => $this->string($edge, 'foreign_key'),
                'Owner key' => $this->string($edge, 'owner_key'),
                'Local key' => $this->string($edge, 'local_key'),
                'Pivot table' => $this->string($edge, 'pivot_table'),
                'Morph type' => $this->string($edge, 'morph_type'),
                'Nullable' => is_bool($nullable) ? ($nullable ? 'yes' : 'no') : 'unknown',
            ]);
        }

        if ($edge->type === EdgeType::LivewireUsesModel) {
            $references = $edge->metadata['references'] ?? [];

            $sections[] = new InspectionSection('livewire', 'Livewire dependency', [
                'Component' => $this->string($edge, 'component_class'),
                'Model' => $this->string($edge, 'model_class'),
                'References' => array_values(array_filter(
                    is_array($references) ? $references : [],
                    'is_string',
                )),
            ]);
        }

        if (! in_array($edge->type, [
            EdgeType::ModelRelation,
            EdgeType::LivewireUsesModel,
            EdgeType::PanelRegistersResource,
            EdgeType::ResourceUsesModel,
        ], true)) {
            $sections[] = $this->httpDetails($edge);
        }

        $warnings = $edge->metadata['warnings'] ?? [];

        $sections[] = new InspectionSection('diagnostics', 'Diagnostics', [
            'Status' => $edge->status->value,
            'Warnings' => array_values(array_filter(
                is_array($warnings) ? $warnings : [],
                'is_string',
            )),
        ]);

        return new InspectionData(
            subjectId: $edge->id->value,
            subjectType: 'edge',
            title: $edge->label !== '' ? $edge->label : $edge->type->value,
            subtitle: sprintf('%s to %s', $source->label ?? '?', $target->label ?? '?'),
            sections: $sections,
        );
    }

    private function httpDetails(Edge $edge): InspectionSection
    {
        $entries = ['Type' => str_replace('_', ' ', $edge->type->value)];

        foreach (['methods' => 'Methods', 'sources' => 'Sources', 'via' => 'Via', 'locations' => 'Locations', 'directives' => 'Directives', 'hows' => 'Rendered through'] as $key => $label) {
            $values = $edge->metadata[$key] ?? [];
            $values = array_values(array_filter(is_array($values) ? $values : [], 'is_string'));

            if ($values !== []) {
                $entries[$label] = $values;
            }
        }

        $lines = $edge->metadata['lines'] ?? [];

        if (is_array($lines) && $lines !== []) {
            $entries['Lines'] = array_values(array_map('strval', $lines));
        }

        foreach (['kind' => 'Kind', 'method' => 'Method', 'source' => 'Resolved by', 'confidence' => 'Detection', 'file' => 'File', 'view' => 'View'] as $key => $label) {
            $value = $this->string($edge, $key);

            if ($value !== null) {
                $entries[$label] = $value;
            }
        }

        return new InspectionSection('http', 'Details', $entries);
    }

    private function string(Edge $edge, string $key): ?string
    {
        $value = $edge->metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
