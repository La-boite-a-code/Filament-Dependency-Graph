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

final class PanelInspector implements NodeInspector
{
    public function supports(Node $node): bool
    {
        return $node->type === NodeType::Panel;
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        $resources = [];

        foreach ($graph->outgoingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::PanelRegistersResource) {
                continue;
            }

            $resource = $graph->node($edge->target);

            if ($resource !== null) {
                $resources[] = $resource->label;
            }
        }

        sort($resources, SORT_STRING);

        $path = $node->metadata['path'] ?? null;
        $domain = $node->metadata['domain'] ?? null;

        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [
                new InspectionSection('identity', 'Identity', [
                    'ID' => is_string($node->metadata['panel_id'] ?? null) ? $node->metadata['panel_id'] : $node->label,
                    'Path' => is_string($path) ? $path : null,
                    'Domain' => is_string($domain) ? $domain : null,
                ]),
                new InspectionSection('resources', 'Resources', [
                    'Resource count' => count($resources),
                    'Resources' => $resources,
                ]),
            ],
        );
    }
}
