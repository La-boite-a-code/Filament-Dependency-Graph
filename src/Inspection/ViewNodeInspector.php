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

/**
 * Inspector for templates, Blade and Filament view classes, package views
 * and dynamic view references.
 */
final class ViewNodeInspector implements NodeInspector
{
    use DescribesViewEdges;

    public function supports(Node $node): bool
    {
        return $node->type->isView();
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        $sections = match ($node->type) {
            NodeType::View => [
                new InspectionSection('identity', 'Template', [
                    'Name' => $this->string($node, 'name'),
                    'Kind' => $this->string($node, 'kind'),
                    'File' => $this->string($node, 'file'),
                ]),
                new InspectionSection('renders', 'Renders', ['References' => $this->viewReferences($node, $graph)]),
                new InspectionSection('used_by', 'Used by', ['Usages' => $this->viewUsages($node, $graph)]),
            ],
            NodeType::BladeComponent, NodeType::FilamentComponent => [
                new InspectionSection('identity', 'Identity', [
                    'Class' => $this->string($node, 'class'),
                    $node->type === NodeType::BladeComponent ? 'Tag' : 'Filament kind' => $this->string($node, 'detail'),
                    'File' => $this->string($node, 'file'),
                ]),
                new InspectionSection('renders', 'Renders', ['Views' => $this->renderedViews($node, $graph)]),
                new InspectionSection('used_by', 'Used by', ['Usages' => $this->viewUsages($node, $graph)]),
            ],
            NodeType::ExternalView => [
                new InspectionSection('identity', 'Package view', [
                    'Reference' => $this->string($node, 'reference'),
                    'Package' => $this->string($node, 'package'),
                    'Missing' => ($node->metadata['missing'] ?? false) === true ? 'yes' : 'no',
                ]),
                new InspectionSection('used_by', 'Used by', ['Usages' => $this->viewUsages($node, $graph)]),
            ],
            default => [
                new InspectionSection('identity', 'Dynamic reference', [
                    'Expression' => $this->string($node, 'expression'),
                    'Directive' => $this->string($node, 'directive'),
                    'Line' => is_int($node->metadata['line'] ?? null) ? $node->metadata['line'] : null,
                ]),
                new InspectionSection('used_by', 'Used by', ['Usages' => $this->viewUsages($node, $graph)]),
            ],
        };

        $warnings = $node->metadata['warnings'] ?? [];

        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [...$sections, new InspectionSection('diagnostics', 'Diagnostics', [
                'Status' => $node->status->value,
                'Badges' => $node->badges,
                'Warnings' => array_values(array_filter(is_array($warnings) ? $warnings : [], 'is_string')),
            ])],
        );
    }

    private function string(Node $node, string $key): ?string
    {
        $value = $node->metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
