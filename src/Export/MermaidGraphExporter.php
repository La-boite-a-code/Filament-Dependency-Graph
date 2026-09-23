<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Export;

use LaBoiteACode\DependencyGraph\Contracts\GraphExporter;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;

final class MermaidGraphExporter implements GraphExporter
{
    /**
     * Edges whose label carries information: relation methods, controller
     * methods, dispatch kinds, and how views are rendered or referenced.
     */
    private const LABELLED_EDGES = [
        EdgeType::ModelRelation,
        EdgeType::RouteHandledByController,
        EdgeType::Dispatches,
        EdgeType::RendersView,
        EdgeType::ViewExtends,
        EdgeType::ViewIncludes,
        EdgeType::ViewUsesComponent,
        EdgeType::ViewRendersLivewire,
    ];

    private const DIRECTIONS = ['LR', 'RL', 'TB', 'BT'];

    public function format(): string
    {
        return 'mermaid';
    }

    public function export(Graph $graph, ExportOptions $options): string
    {
        $direction = in_array($options->mermaidDirection, self::DIRECTIONS, true)
            ? $options->mermaidDirection
            : 'LR';

        $lines = [];

        if ($graph->nodeCount() > $options->mermaidNodeWarningThreshold) {
            $lines[] = sprintf(
                '%%%% Warning: %d nodes exceed the readability threshold of %d, consider focus mode or filters.',
                $graph->nodeCount(),
                $options->mermaidNodeWarningThreshold,
            );
        }

        $lines[] = 'flowchart ' . $direction;

        foreach ($graph->nodes as $node) {
            $lines[] = sprintf(
                '    %s["%s"]',
                $this->identifier($node->id->value),
                $this->escapeLabel($node->label),
            );
        }

        foreach ($graph->edges as $edge) {
            $source = $this->identifier($edge->source->value);
            $target = $this->identifier($edge->target->value);

            if ($options->includeEdgeLabels && in_array($edge->type, self::LABELLED_EDGES, true) && $edge->label !== '') {
                $lines[] = preg_match('/^[A-Za-z0-9_ .-]+$/', $edge->label) === 1
                    ? sprintf('    %s -- %s --> %s', $source, $edge->label, $target)
                    : sprintf('    %s -->|"%s"| %s', $source, $this->escapeEdgeLabel($edge->label), $target);
            } else {
                $lines[] = sprintf('    %s --> %s', $source, $target);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Mermaid identifiers only keep word characters; every other character
     * maps to an underscore so identifiers stay deterministic.
     */
    private function identifier(string $nodeId): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]/', '_', $nodeId);
    }

    private function escapeLabel(string $label): string
    {
        $label = str_replace(['"', "\n", "\r"], ['#quot;', ' ', ' '], $label);

        return trim($label);
    }

    /**
     * Quoted edge labels keep directives and component tags readable:
     * Mermaid entity codes stand for quotes and angle brackets.
     */
    private function escapeEdgeLabel(string $label): string
    {
        return str_replace(['"', '<', '>', "\n", "\r"], ['#quot;', '#lt;', '#gt;', ' ', ' '], $label);
    }
}
