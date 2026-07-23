<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection;

use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionSection;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;

final class ResourceInspector implements NodeInspector
{
    public function supports(Node $node): bool
    {
        return $node->type === NodeType::Resource;
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        $pages = [];

        foreach ($this->arrayList($node, 'pages') as $page) {
            $pages[] = sprintf(
                '%s: %s (%s)',
                (string) ($page['name'] ?? '?'),
                (string) ($page['class'] ?? '?'),
                (string) ($page['type'] ?? 'unknown'),
            );
        }

        $relationManagers = [];

        foreach ($this->arrayList($node, 'relation_managers') as $manager) {
            $relationship = $manager['relationship'] ?? null;

            $relationManagers[] = is_string($relationship)
                ? sprintf('%s (%s)', (string) ($manager['class'] ?? '?'), $relationship)
                : (string) ($manager['class'] ?? '?');
        }

        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [
                new InspectionSection('identity', 'Identity', [
                    'Class' => $this->string($node, 'class'),
                    'Model' => $this->string($node, 'model_class'),
                    'Panels' => $this->stringList($node, 'panel_ids'),
                ]),
                new InspectionSection('labels', 'Labels', [
                    'Label' => $this->string($node, 'label'),
                    'Plural label' => $this->string($node, 'plural_label'),
                ]),
                new InspectionSection('navigation', 'Navigation', [
                    'Group' => $this->string($node, 'navigation_group'),
                    'Icon' => $this->string($node, 'navigation_icon'),
                ]),
                new InspectionSection('pages', 'Pages', [
                    'Pages' => $pages,
                ]),
                new InspectionSection('relation_managers', 'Relation managers', [
                    'Relation managers' => $relationManagers,
                ]),
                new InspectionSection('diagnostics', 'Diagnostics', [
                    'Status' => $node->status->value,
                    'Warnings' => $this->stringList($node, 'warnings'),
                ]),
            ],
        );
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

    /**
     * @return list<array<string, mixed>>
     */
    private function arrayList(Node $node, string $key): array
    {
        $values = $node->metadata[$key] ?? [];

        return array_values(array_filter(
            is_array($values) ? $values : [],
            'is_array',
        ));
    }
}
