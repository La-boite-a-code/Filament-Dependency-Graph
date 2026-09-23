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

it('labels controller methods and dispatch kinds in the HTTP map', function (): void {
    $graph = fakeGraph(
        [
            fakeNode('route:POST:/orders', label: 'POST /orders'),
            fakeNode('controller:app.order-controller', label: 'OrderController'),
            fakeNode('form-request:app.store-order', label: 'StoreOrder'),
            fakeNode('job:app.ship-order', label: 'ShipOrder'),
        ],
        [
            fakeEdge('route:POST:/orders', 'controller:app.order-controller', EdgeType::RouteHandledByController, 'store'),
            fakeEdge('controller:app.order-controller', 'form-request:app.store-order', EdgeType::ControllerValidatesWith, 'store'),
            fakeEdge('controller:app.order-controller', 'job:app.ship-order', EdgeType::Dispatches, 'job'),
        ],
    );

    $output = (new MermaidGraphExporter)->export($graph, new ExportOptions);

    expect($output)->toContain('route_POST__orders -- store --> controller_app_order_controller')
        ->and($output)->toContain('controller_app_order_controller -- job --> job_app_ship_order')
        ->and($output)->toContain('controller_app_order_controller --> form_request_app_store_order');
});

it('keeps directives and component tags readable on view edges', function (): void {
    $graph = fakeGraph(
        [fakeNode('view:orders.index', label: 'orders.index'), fakeNode('view:partials.row', label: 'partials.row'), fakeNode('blade-component:alert', label: 'Alert')],
        [
            fakeEdge('view:orders.index', 'view:partials.row', EdgeType::ViewIncludes, '@include, @each'),
            fakeEdge('view:orders.index', 'blade-component:alert', EdgeType::ViewUsesComponent, '<x-alert>'),
        ],
    );

    $output = (new MermaidGraphExporter)->export($graph, new ExportOptions);

    expect($output)->toContain('view_orders_index -->|"@include, @each"| view_partials_row')
        ->and($output)->toContain('view_orders_index -->|"#lt;x-alert#gt;"| blade_component_alert');
});
