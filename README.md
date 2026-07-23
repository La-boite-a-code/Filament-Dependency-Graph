# Filament Dependency Graph

> The visual architecture explorer for Filament.

Explore models, resources, panels and relationships from one visual workspace. Filament Dependency Graph automatically discovers the structure of a Laravel and Filament application and presents it through an interactive, navigable interface: a graph, a tree, a table, a contextual inspector, search, focus mode, filtering and exports.

[![Tests](https://github.com/la-boite-a-code/filament-dependency-graph/actions/workflows/tests.yml/badge.svg)](https://github.com/la-boite-a-code/filament-dependency-graph/actions/workflows/tests.yml)
[![Static analysis](https://github.com/la-boite-a-code/filament-dependency-graph/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/la-boite-a-code/filament-dependency-graph/actions/workflows/static-analysis.yml)
[![Code style](https://github.com/la-boite-a-code/filament-dependency-graph/actions/workflows/code-style.yml/badge.svg)](https://github.com/la-boite-a-code/filament-dependency-graph/actions/workflows/code-style.yml)

## The problem

Large Laravel and Filament applications are hard to understand because their structure is scattered across models, relationships, resources, panels, relation managers, policies, traits, casts and morph maps. A developer joining an existing project inspects dozens of files before understanding which models exist, how they connect, which resources expose them and where circular dependencies live. Static diagrams rot; manual documentation is never complete.

## The solution

Install the package, register the plugin, open one page, and immediately answer:

- Which models exist, and which resources expose them?
- Which panels contain those resources?
- How are the models connected, and through which relation types?
- Which models have no resource? Which are isolated?
- Where do circular dependencies exist?
- What surrounds this specific model?

Screenshots will be added with the first tagged release.

## Features

- Automatic discovery of Eloquent models, relations, Filament panels, resources, pages and relation managers, with zero package-specific configuration on standard applications.
- All Eloquent relation types: belongsTo, hasOne, hasMany, belongsToMany, hasOneThrough, hasManyThrough, morphTo, morphOne, morphMany, morphToMany, morphedByMany.
- Interactive graph (Cytoscape.js) with hierarchical and force-directed layouts, zoom, pan, selection and dark mode.
- Tree and table views for the same data, usable without the graph renderer.
- Contextual inspector for models, resources, panels and relation edges: keys, pivot tables, morph metadata, traits, casts, diagnostics.
- Search across class names, labels, tables, namespaces, panels and relation methods, with deterministic ranking.
- Focus mode with configurable depth and direction, shareable through the URL query string.
- Filters: panels, node types, relation types, namespace, vendor or application ownership, orphans only, circular dependencies only, models without resources only.
- Deterministic JSON and Mermaid exports, from the UI or the CLI.
- Snapshot cache with dedicated artisan commands.
- Read-only by design: the package never modifies your application or queries business records.
- Resilient discovery: one broken model or throwing relation method becomes a warning, never a crash.

## Compatibility

The CI matrix is the source of truth.

| Package | PHP | Laravel | Filament |
| ------- | --- | ------- | -------- |
| 0.x     | 8.3, 8.4 | 12.x, 13.x | 4.x, 5.x |

## Installation

```bash
composer require laboiteacode/filament-dependency-graph
```

Register the plugin in your panel provider:

```php
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            DependencyGraphPlugin::make(),
        ]);
}
```

That is all: open `/admin/dependency-graph` in your local environment.

## Security warning

Class names, table names and relationship metadata are sensitive architecture information. The page is only visible when `app()->isLocal()` returns true. Never expose it publicly.

To enable it elsewhere, take an explicit decision:

```php
DependencyGraphPlugin::make()
    ->visible(fn (): bool => auth()->user()?->can('viewDependencyGraph') === true);
```

## Configuration

Publish the configuration file when you need to adjust discovery:

```bash
php artisan vendor:publish --tag=filament-dependency-graph-config
```

Key options:

```php
return [
    'default_scope' => GraphScope::Filament,
    'model_paths' => [app_path('Models')],
    'exclude' => [
        'classes' => [],
        'namespaces' => [],
        'tables' => [],
        'relations' => [],
    ],
    'discovery' => [
        'docblocks' => true,
        'heuristic_relation_invocation' => false,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
];
```

The plugin exposes the same knobs fluently:

```php
DependencyGraphPlugin::make()
    ->defaultScope(GraphScope::Filament)
    ->defaultDepth(2)
    ->allowLaravelScope()
    ->scanVendorModels(false)
    ->excludeModels([AuditLog::class])
    ->registerModelPath(app_path('Domain'))
    ->registerExporter(new MyGraphvizExporter())
    ->registerInspector(new MyAuditableInspector());
```

## Scopes

- **Filament scope** (default): starts from the resources registered in the selected panels and includes their models plus related models up to the configured depth.
- **Laravel scope**: every discovered Eloquent model, including models that no resource exposes.

## Keyboard shortcuts

| Key | Action |
| --- | ------ |
| `/` | Focus search |
| `Esc` | Close inspector or exit focus mode |
| `F` | Focus selected node |
| `R` | Reset graph |
| `G` | Graph view |
| `T` | Tree view |
| `L` | Table view |
| `E` | Export JSON |

## Exports

From the CLI:

```bash
php artisan filament-dependency-graph:export \
    --format=json \
    --scope=filament \
    --panel=admin \
    --output=storage/app/dependency-graph.json

php artisan filament-dependency-graph:export \
    --format=mermaid \
    --scope=laravel \
    --output=docs/dependency-graph.mmd
```

Programmatically:

```php
use LaBoiteACode\DependencyGraph\Facades\DependencyGraph;

$snapshot = DependencyGraph::discover();
$graph = DependencyGraph::graph();
$json = DependencyGraph::export('json');
$mermaid = DependencyGraph::export('mermaid');
```

Both exports are deterministic: same application state, same output.

## Cache

Discovery relies on reflection and file scanning, so snapshots are cached.

```bash
php artisan filament-dependency-graph:cache
php artisan filament-dependency-graph:clear
```

The cache key includes the package schema version, the runtime versions and every discovery setting, so configuration changes never serve stale graphs.

## Testing

```bash
composer test
composer analyse
composer lint
```

The suite runs on Pest with Orchestra Testbench against a realistic fixture domain covering every supported relation type, multi-panel resources and failure scenarios.

## Architecture

The codebase is layered: a framework-agnostic domain (graph model, DTOs, algorithms), an application layer (use cases), infrastructure (discovery, cache, exporters) and a Filament presentation layer. Filament 4 and 5 differences are isolated behind a compatibility adapter. Architecture decision records live in [docs/adr](docs/adr).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Please report security issues privately as described in [SECURITY.md](SECURITY.md).

## License

Open source under the [MIT license](LICENSE.md).
