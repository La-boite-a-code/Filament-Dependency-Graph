<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;
use LaBoiteACode\DependencyGraph\Export\MermaidGraphExporter;

it('identifies itself as the mermaid format', function (): void {
    expect((new MermaidGraphExporter)->format())->toBe('mermaid');
});

it('renders a flowchart with sanitized identifiers', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:app.models.order', label: 'Order'), fakeNode('model:app.models.customer', label: 'Customer')],
        [fakeEdge('model:app.models.order', 'model:app.models.customer', label: 'customer')],
    );

    $output = (new MermaidGraphExporter)->export($graph, new ExportOptions);

    expect($output)->toContain('flowchart LR')
        ->and($output)->toContain('model_app_models_order["Order"]')
        ->and($output)->toContain('model_app_models_order -- customer --> model_app_models_customer');
});

it('escapes quotes in labels', function (): void {
    $graph = fakeGraph([fakeNode('model:a', label: 'Order "special"')]);

    $output = (new MermaidGraphExporter)->export($graph, new ExportOptions);

    expect($output)->toContain('#quot;special#quot;')
        ->and($output)->not->toContain('"Order "special""');
});

it('omits edge labels for structural edges and when disabled', function (): void {
    $graph = fakeGraph(
        [fakeNode('panel:admin'), fakeNode('resource:r'), fakeNode('model:a'), fakeNode('model:b')],
        [
            fakeEdge('panel:admin', 'resource:r', EdgeType::PanelRegistersResource, 'registers'),
            fakeEdge('model:a', 'model:b', label: 'related'),
        ],
    );

    $withLabels = (new MermaidGraphExporter)->export($graph, new ExportOptions);
    $withoutLabels = (new MermaidGraphExporter)->export($graph, new ExportOptions(includeEdgeLabels: false));

    expect($withLabels)->toContain('panel_admin --> resource_r')
        ->and($withLabels)->toContain('-- related -->')
        ->and($withoutLabels)->not->toContain('-- related -->');
});

it('warns when the graph exceeds the readability threshold', function (): void {
    $nodes = [];

    for ($index = 0; $index < 5; $index++) {
        $nodes[] = fakeNode('model:node' . $index);
    }

    $output = (new MermaidGraphExporter)->export(
        fakeGraph($nodes),
        new ExportOptions(mermaidNodeWarningThreshold: 3),
    );

    expect($output)->toContain('%% Warning:');
});

it('falls back to LR for invalid directions and produces deterministic output', function (): void {
    $graph = fakeGraph([fakeNode('model:a')]);

    $output = (new MermaidGraphExporter)->export($graph, new ExportOptions(mermaidDirection: 'DIAGONAL'));

    expect($output)->toContain('flowchart LR')
        ->and($output)->toBe((new MermaidGraphExporter)->export($graph, new ExportOptions(mermaidDirection: 'DIAGONAL')));
});
