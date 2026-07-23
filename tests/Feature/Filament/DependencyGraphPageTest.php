<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;

function makePage(): DependencyGraphPage
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config()->set('filament-dependency-graph.model_paths', [dirname(__DIR__, 2) . '/Fixtures/Models']);

    $page = new DependencyGraphPage;
    $page->mount();

    return $page;
}

it('produces a graph payload for the frontend', function (): void {
    $page = makePage();

    $payload = $page->getGraphPayload();

    expect($payload['error'])->toBeNull()
        ->and($payload['stats']['nodes'])->toBeGreaterThan(0)
        ->and($payload['graph']['nodes'][0])->toHaveKeys(['id', 'type', 'label', 'badges']);
});

it('opens the inspector for a selected model node', function (): void {
    $page = makePage();
    $page->scope = 'laravel';

    $page->selectNode('model:la-boite-a-code.dependency-graph.tests.fixtures.models.order');

    $inspection = $page->getInspection();

    expect($inspection)->not->toBeNull()
        ->and($inspection['title'])->toBe('Order')
        ->and(array_column($inspection['sections'], 'key'))->toContain('identity', 'relationships', 'database');
});

it('opens the inspector for a relation edge', function (): void {
    $page = makePage();
    $page->scope = 'laravel';

    $payload = $page->getGraphPayload();

    $edgeId = null;

    foreach ($payload['graph']['edges'] as $edge) {
        if ($edge['type'] === 'model_relation') {
            $edgeId = $edge['id'];

            break;
        }
    }

    expect($edgeId)->not->toBeNull();

    $page->selectEdge($edgeId);

    $inspection = $page->getInspection();

    expect($inspection)->not->toBeNull()
        ->and($inspection['subject_type'])->toBe('edge')
        ->and(array_column($inspection['sections'], 'key'))->toContain('relation', 'keys');
});

it('returns grouped search results', function (): void {
    $page = makePage();
    $page->search = 'Order';

    $groups = $page->getSearchResults();

    expect($groups)->not->toBeEmpty();

    $types = array_column($groups, 'type');

    expect($types)->toContain('model');
});

it('resets every filter to defaults', function (): void {
    $page = makePage();

    $page->scope = 'laravel';
    $page->focus = 'model:x';
    $page->depth = 3;
    $page->onlyOrphans = true;
    $page->namespaceFilter = 'App';
    $page->hiddenNodeTypes = ['panel'];

    $page->resetGraph();

    expect($page->focus)->toBeNull()
        ->and($page->depth)->toBeNull()
        ->and($page->onlyOrphans)->toBeFalse()
        ->and($page->namespaceFilter)->toBe('')
        ->and($page->hiddenNodeTypes)->toBe([])
        ->and($page->scope)->toBe('filament');
});

it('builds a cycle safe tree', function (): void {
    $page = makePage();
    $page->scope = 'laravel';
    $page->selectNode('model:la-boite-a-code.dependency-graph.tests.fixtures.models.user');
    $page->depth = 3;

    $tree = $page->getTree();

    expect($tree)->not->toBeEmpty()
        ->and($tree[0]['label'])->toBe('User');
});

it('exposes table rows for models, relations and resources', function (): void {
    $page = makePage();
    $page->scope = 'laravel';

    $tables = $page->getTables();

    expect($tables['models'])->not->toBeEmpty()
        ->and($tables['relations'])->not->toBeEmpty()
        ->and($tables['resources'])->not->toBeEmpty()
        ->and($tables['models'][0])->toHaveKeys(['label', 'table', 'outgoing', 'incoming', 'status']);
});

it('streams exports as downloads', function (): void {
    $page = makePage();

    $response = $page->export('json');

    expect($response->getStatusCode())->toBe(200);
});

it('keeps focus state in url bindable properties', function (): void {
    $page = makePage();

    $page->selectNode('model:la-boite-a-code.dependency-graph.tests.fixtures.models.order');
    $page->focusOnNode();

    expect($page->focus)->toBe('model:la-boite-a-code.dependency-graph.tests.fixtures.models.order')
        ->and($page->depth)->toBe(2);
});
