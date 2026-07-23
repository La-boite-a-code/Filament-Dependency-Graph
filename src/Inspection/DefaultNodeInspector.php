<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection;

use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionSection;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;

/**
 * Delegates to the first registered inspector supporting the node. Custom
 * inspectors can be prepended so they take precedence over the built-in
 * ones. Nodes supported by no inspector fall back to a generic summary.
 */
final class DefaultNodeInspector implements NodeInspector
{
    /** @var list<NodeInspector> */
    private array $inspectors;

    /**
     * @param  list<NodeInspector>  $inspectors
     */
    public function __construct(array $inspectors)
    {
        $this->inspectors = $inspectors;
    }

    public function prepend(NodeInspector $inspector): void
    {
        array_unshift($this->inspectors, $inspector);
    }

    public function supports(Node $node): bool
    {
        return true;
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        foreach ($this->inspectors as $inspector) {
            if ($inspector->supports($node)) {
                return $inspector->inspect($node, $graph);
            }
        }

        return $this->generic($node);
    }

    private function generic(Node $node): InspectionData
    {
        $entries = [];

        foreach ($node->metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $entries[$key] = $value === null ? null : (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
            }
        }

        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [
                new InspectionSection('identity', 'Identity', $entries),
                new InspectionSection('diagnostics', 'Diagnostics', [
                    'Status' => $node->status->value,
                    'Badges' => $node->badges,
                ]),
            ],
        );
    }
}
