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
    | when no Filament resource exposes it. The HTTP scope starts from the
    | application routes; it is controlled by the "http.enabled" option. The
    | Views scope maps Blade templates; it is controlled by "views.enabled".
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
    | Livewire component discovery
    |--------------------------------------------------------------------------
    |
    | Components are scanned independently from Filament panels and appear
    | in the Laravel scope. The legacy app/Http/Livewire convention remains
    | enabled alongside the current app/Livewire directory.
    |
    */

    'livewire' => [
        'enabled' => true,
        'paths' => [
            app_path('Livewire'),
            app_path('Http/Livewire'),
        ],
        'namespaces' => [
            'App\\Livewire\\',
            'App\\Http\\Livewire\\',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP map
    |--------------------------------------------------------------------------
    |
    | Routes, controllers, form requests, route-bound models, policies,
    | events, listeners, and the jobs, mailables and notifications they
    | dispatch. Controller actions are never called: everything comes from
    | the router, reflection and a static reading of the source code.
    |
    | Only application routes are mapped: controllers under the controller
    | namespaces, closures and view routes defined outside vendor/, and
    | full-page Livewire components. Exclusions accept Str::is() patterns.
    |
    | Form request rules are only read when "form_request_rules" is enabled,
    | because rules() may depend on the current request or the database.
    |
    */

    'http' => [
        'enabled' => true,
        'controller_namespaces' => [
            'App\\Http\\Controllers\\',
        ],
        // Listeners, jobs, events and injected classes followed one level.
        'application_namespaces' => [
            'App\\',
        ],
        'include_vendor_routes' => false,
        'exclude' => [
            // Route names, for example 'horizon.*'.
            'names' => [],
            // Route URIs, for example '_debugbar/*'.
            'uris' => [],
        ],
        'follow_injected_classes' => true,
        'form_request_rules' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | View map
    |--------------------------------------------------------------------------
    |
    | Blade templates, Blade class components, Livewire components, Filament
    | classes with a custom view, mailables and notifications, and how they
    | include, extend and render each other. Templates are read as text and
    | names are resolved by the framework: nothing is compiled or rendered.
    |
    | Package views used by the application are shown as leaves; enable
    | "explore_package_views" to read them as well.
    |
    */

    'views' => [
        'enabled' => true,
        // resources/views/vendor/** overrides of package views.
        'include_vendor_overrides' => false,
        'explore_package_views' => false,
        'blade_component_paths' => [
            app_path('View/Components'),
        ],
        'filament_paths' => [
            app_path('Filament'),
        ],
        'mail_paths' => [
            app_path('Mail'),
            app_path('Notifications'),
        ],
        'exclude' => [
            // View names, Str::is() patterns, for example 'errors.*'.
            'views' => [],
        ],
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
