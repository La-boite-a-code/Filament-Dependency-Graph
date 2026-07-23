<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\SchemaVersion;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;
use LaBoiteACode\DependencyGraph\Export\JsonGraphExporter;

it('identifies itself as the json format', function (): void {
    expect((new JsonGraphExporter)->format())->toBe('json');
});

it('contains the schema version and top level structure', function (): void {
    $graph = fakeGraph([fakeNode('model:a')], []);

    $decoded = json_decode((new JsonGraphExporter)->export($graph, new ExportOptions(
        scope: 'filament',
        generatedAt: new DateTimeImmutable('2026-07-23T10:00:00+00:00'),
        environment: ['laravel' => '13.x', 'filament' => '5.x', 'php' => '8.4'],
    )), true);

    expect($decoded['schemaVersion'])->toBe(SchemaVersion::CURRENT)
        ->and($decoded['generatedAt'])->toBe('2026-07-23T10:00:00+00:00')
        ->and($decoded['scope'])->toBe('filament')
        ->and($decoded['environment']['laravel'])->toBe('13.x')
        ->and($decoded)->toHaveKeys(['nodes', 'edges', 'warnings', 'filters']);
});

it('produces deterministic output', function (): void {
    $graph = fakeGraph(
        [fakeNode('model:a'), fakeNode('model:b')],
        [fakeEdge('model:a', 'model:b')],
    );

    $options = new ExportOptions(generatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    expect((new JsonGraphExporter)->export($graph, $options))
        ->toBe((new JsonGraphExporter)->export($graph, $options));
});

it('serializes warnings without any secret material', function (): void {
    $graph = fakeGraph([fakeNode('model:a')]);

    $json = (new JsonGraphExporter)->export($graph, new ExportOptions(
        warnings: [new DiscoveryWarning(type: 'model_discovery', message: 'partial')],
    ));

    $decoded = json_decode($json, true);

    expect($decoded['warnings'][0]['type'])->toBe('model_discovery')
        ->and($json)->not->toContain('DB_PASSWORD')
        ->and($json)->not->toContain('APP_KEY');
});

it('supports compact output', function (): void {
    $graph = fakeGraph([fakeNode('model:a')]);

    $compact = (new JsonGraphExporter)->export($graph, new ExportOptions(prettyPrint: false));

    expect($compact)->not->toContain("\n");
});
