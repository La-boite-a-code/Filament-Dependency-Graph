<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection;

use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionSection;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Support\ClassName;

final class ModelInspector implements NodeInspector
{
    public function supports(Node $node): bool
    {
        return $node->type === NodeType::Model;
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [
                $this->identity($node),
                $this->filamentUsage($node, $graph),
                $this->relationships($node, $graph),
                $this->database($node),
                $this->behavior($node),
                $this->diagnostics($node),
            ],
        );
    }

    private function identity(Node $node): InspectionSection
    {
        return new InspectionSection('identity', 'Identity', [
            'Class' => $this->string($node, 'class'),
            'Namespace' => $this->string($node, 'namespace'),
            'Table' => $this->string($node, 'table'),
            'Connection' => $this->string($node, 'connection'),
        ]);
    }

    private function filamentUsage(Node $node, Graph $graph): InspectionSection
    {
        $resources = [];
        $panels = [];
        $relationManagers = [];

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::ResourceUsesModel) {
                continue;
            }

            $resource = $graph->node($edge->source);

            if ($resource === null) {
                continue;
            }

            $resources[] = $resource->label;

            $panelIds = $resource->metadata['panel_ids'] ?? [];

            foreach (is_array($panelIds) ? $panelIds : [] as $panelId) {
                if (is_string($panelId)) {
                    $panels[] = $panelId;
                }
            }

            $managers = $resource->metadata['relation_managers'] ?? [];

            foreach (is_array($managers) ? $managers : [] as $manager) {
                if (is_array($manager) && is_string($manager['class'] ?? null)) {
                    $relationManagers[] = ClassName::shortName($manager['class']);
                }
            }
        }

        sort($resources, SORT_STRING);
        $panels = array_values(array_unique($panels));
        sort($panels, SORT_STRING);
        sort($relationManagers, SORT_STRING);

        return new InspectionSection('filament', 'Filament usage', [
            'Resources' => $resources,
            'Panels' => $panels,
            'Relation managers' => $relationManagers,
        ]);
    }

    private function relationships(Node $node, Graph $graph): InspectionSection
    {
        $outgoing = [];
        $incoming = [];

        foreach ($graph->outgoingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::ModelRelation) {
                continue;
            }

            $target = $graph->node($edge->target);
            $relationLabel = $edge->metadata['relation_label'] ?? null;

            $outgoing[] = sprintf(
                '%s: %s %s',
                $edge->label,
                is_string($relationLabel) ? $relationLabel : '',
                $target->label ?? '?',
            );
        }

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::ModelRelation) {
                continue;
            }

            $source = $graph->node($edge->source);
            $relationLabel = $edge->metadata['relation_label'] ?? null;

            $incoming[] = sprintf(
                '%s.%s: %s',
                $source->label ?? '?',
                $edge->label,
                is_string($relationLabel) ? $relationLabel : '',
            );
        }

        sort($outgoing, SORT_STRING);
        sort($incoming, SORT_STRING);

        return new InspectionSection('relationships', 'Relationships', [
            'Outgoing' => $outgoing,
            'Incoming' => $incoming,
        ]);
    }

    private function database(Node $node): InspectionSection
    {
        return new InspectionSection('database', 'Database', [
            'Primary key' => $this->string($node, 'primary_key'),
            'Key type' => $this->string($node, 'key_type'),
            'Incrementing' => $this->bool($node, 'incrementing'),
            'Timestamps' => $this->bool($node, 'timestamps'),
            'Soft deletes' => $this->bool($node, 'soft_deletes'),
        ]);
    }

    private function behavior(Node $node): InspectionSection
    {
        $casts = [];
        $rawCasts = $node->metadata['casts'] ?? [];

        foreach (is_array($rawCasts) ? $rawCasts : [] as $attribute => $cast) {
            $casts[] = sprintf('%s: %s', (string) $attribute, (string) $cast);
        }

        return new InspectionSection('behavior', 'Model behavior', [
            'Traits' => array_map(
                static fn (string $trait): string => ClassName::shortName($trait),
                $this->stringList($node, 'traits'),
            ),
            'Casts' => $casts,
            'Fillable' => $this->stringList($node, 'fillable'),
            'Guarded' => $this->stringList($node, 'guarded'),
            'Hidden' => $this->stringList($node, 'hidden'),
            'Visible' => $this->stringList($node, 'visible'),
        ]);
    }

    private function diagnostics(Node $node): InspectionSection
    {
        return new InspectionSection('diagnostics', 'Diagnostics', [
            'Status' => $node->status->value,
            'Warnings' => $this->stringList($node, 'warnings'),
        ]);
    }

    private function string(Node $node, string $key): ?string
    {
        $value = $node->metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function bool(Node $node, string $key): string
    {
        return ($node->metadata[$key] ?? false) === true ? 'yes' : 'no';
    }

    /**
     * @return list<string>
     */
    private function stringList(Node $node, string $key): array
    {
        $values = $node->metadata[$key] ?? [];

        return array_values(array_filter(
            is_array($values) ? $values : [],
            'is_string',
        ));
    }
}
