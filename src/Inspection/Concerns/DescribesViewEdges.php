<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection\Concerns;

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;

/**
 * Human readable lists of the view edges around a node, shared by the
 * inspectors of views and of the classes rendering them.
 */
trait DescribesViewEdges
{
    private const VIEW_REFERENCE_EDGES = [
        EdgeType::ViewExtends,
        EdgeType::ViewIncludes,
        EdgeType::ViewUsesComponent,
        EdgeType::ViewRendersLivewire,
        EdgeType::ViewReferencesDynamic,
    ];

    /**
     * Views an owner renders: "orders.index (render)".
     *
     * @return list<string>
     */
    private function renderedViews(Node $node, Graph $graph): array
    {
        $views = [];

        foreach ($graph->outgoingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::RendersView) {
                continue;
            }

            $methods = $edge->metadata['methods'] ?? [];
            $methods = is_array($methods) && $methods !== [] ? ' @ ' . implode(', ', array_map('strval', $methods)) : '';

            $views[] = sprintf('%s (%s%s)', $graph->node($edge->target)->label ?? $edge->target->value, $edge->label, $methods);
        }

        sort($views, SORT_STRING);

        return $views;
    }

    /**
     * What a template references: "@include orders.partials.row (lines 9, 12)".
     *
     * @return list<string>
     */
    private function viewReferences(Node $node, Graph $graph): array
    {
        $references = [];

        foreach ($graph->outgoingEdges($node->id) as $edge) {
            if (in_array($edge->type, self::VIEW_REFERENCE_EDGES, true)) {
                $references[] = sprintf('%s %s%s', $edge->label, $graph->node($edge->target)->label ?? $edge->target->value, $this->lines($edge));
            }
        }

        return $references;
    }

    /**
     * Templates and owners using a node: "orders.index: <x-alert> (line 5)".
     *
     * @return list<string>
     */
    private function viewUsages(Node $node, Graph $graph): array
    {
        $usages = [];

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::RendersView && ! in_array($edge->type, self::VIEW_REFERENCE_EDGES, true)) {
                continue;
            }

            $usages[] = sprintf('%s: %s%s', $graph->node($edge->source)->label ?? $edge->source->value, $edge->label, $this->lines($edge));
        }

        sort($usages, SORT_STRING);

        return $usages;
    }

    private function lines(Edge $edge): string
    {
        $lines = $edge->metadata['lines'] ?? [];

        if (! is_array($lines) || $lines === []) {
            return '';
        }

        return sprintf(' (%s %s)', count($lines) === 1 ? 'line' : 'lines', implode(', ', array_map('strval', $lines)));
    }
}
