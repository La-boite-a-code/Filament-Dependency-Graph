<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the dependency graph page is hidden everywhere and the
    | plugin does not expose any interface. The programmatic API and the
    | artisan commands keep working.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Every entry can also be set fluently on the plugin, which then wins over
    | this file: navigationLabel(), navigationIcon(), activeNavigationIcon(),
    | navigationGroup(), navigationSort(), navigationParentItem(),
    | navigationBadge() and registerNavigation().
    |
    */

    'navigation' => [
        // Defaults to the translated "Dependency Graph" label.
        'label' => null,
        'icon' => 'heroicon-o-share',
        'active_icon' => null,
        'group' => null,
        'sort' => null,
        'parent_item' => null,
        'register' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    |
    | The page renders full width by default so large graphs get the whole
    | viewport. Accepts any Filament\Support\Enums\Width value ('full', '7xl',
    | 'screen-2xl', ...) or null to fall back to the panel default. The page
    | can also live inside a cluster and under a custom slug.
    |
    */

    'page' => [
        'slug' => 'dependency-graph',
        'cluster' => null,
        'max_content_width' => 'full',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | The Filament scope starts from the resources registered in the selected
    | panels. The Laravel scope shows every discovered Eloquent model, even
    | when no Filament resource exposes it.
    |
    */

    'default_scope' => GraphScope::Filament,

    'laravel_scope_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Model discovery
    |--------------------------------------------------------------------------
    */

    'model_paths' => [
        app_path('Models'),
    ],

    'model_namespaces' => [
        'App\\Models\\',
    ],

    'exclude' => [
        'classes' => [],
        'namespaces' => [],
        'tables' => [],
        // Entries formatted as "App\Models\Order::customer".
        'relations' => [],
    ],

    'vendor_models' => [
        'enabled' => false,
        'namespaces' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery behavior
    |--------------------------------------------------------------------------
    |
    | Heuristic relation invocation calls untyped methods to check whether
    | they return a relation. It stays disabled by default because invoking
    | arbitrary methods may trigger application side effects.
    |
    */

    'discovery' => [
        'relations' => true,
        'database_schema' => true,
        'docblocks' => true,
        'heuristic_relation_invocation' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph defaults
    |--------------------------------------------------------------------------
    */

    'graph' => [
        'default_depth' => 2,
        'default_direction' => 'both',
        'default_layout' => 'hierarchical',
        'show_panel_nodes' => true,
        'show_resource_nodes' => true,
        'show_orphans' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot cache
    |--------------------------------------------------------------------------
    |
    | The cache stores the discovered application snapshot, never rendered
    | output. It is bypassed automatically in the testing environment.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Architecture metadata is sensitive. The page is only visible in the
    | local environment unless you explicitly configure another rule through
    | the plugin visibility callback.
    |
    */

    'authorization' => [
        'local_only' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    */

    'exports' => [
        'json' => true,
        'mermaid' => true,
    ],

];
