<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection;

use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionSection;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Inspection\Concerns\DescribesViewEdges;

final class LivewireComponentInspector implements NodeInspector
{
    use DescribesViewEdges;

    public function supports(Node $node): bool
    {
        return $node->type === NodeType::LivewireComponent;
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        $models = [];
        $references = $node->metadata['model_references'] ?? [];

        if (is_array($references)) {
            foreach ($references as $class => $locations) {
                if (! is_string($class)) {
                    continue;
                }

                $locations = array_values(array_filter(
                    is_array($locations) ? $locations : [],
                    'is_string',
                ));

                $models[] = $locations === []
                    ? $class
                    : sprintf('%s (%s)', $class, implode(', ', $locations));
            }
        }

        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [
                new InspectionSection('identity', 'Identity', [
                    'Class' => $this->string($node, 'class'),
                    'Alias' => $this->string($node, 'alias'),
                    'File' => $this->string($node, 'file'),
                ]),
                new InspectionSection('rendering', 'Rendering', [
                    'View' => $this->string($node, 'view'),
                ]),
                new InspectionSection('public_api', 'Public API', [
                    'Properties' => $this->stringList($node, 'public_properties'),
                    'Methods' => $this->stringList($node, 'public_methods'),
                ]),
                new InspectionSection('models', 'Model dependencies', [
                    'Models' => $models,
                ]),
                ...$this->viewsSection($node, $graph),
                new InspectionSection('diagnostics', 'Diagnostics', [
                    'Status' => $node->status->value,
                    'Warnings' => $this->stringList($node, 'warnings'),
                ]),
            ],
        );
    }

    /**
     * @return list<InspectionSection>
     */
    private function viewsSection(Node $node, Graph $graph): array
    {
        $views = array_filter([
            'Renders' => $this->renderedViews($node, $graph),
            'Used in' => $this->viewUsages($node, $graph),
        ]);

        return $views === [] ? [] : [new InspectionSection('views', 'Views', $views)];
    }

    private function string(Node $node, string $key): ?string
    {
        $value = $node->metadata[$key] ?? null;

        return is_string($value) ? $value : null;
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
