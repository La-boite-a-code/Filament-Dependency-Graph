# View map design

## Goal

Map the Blade views of the application: which views, components and Livewire components a page renders (top-down), and where a view or a component is used (bottom-up). The map is a fourth scope, `views`, bridged from the HTTP scope, and ships in the same release as the HTTP map (1.2.0).

## Decisions

| Topic | Decision |
|---|---|
| Direction | Both. Top-down in the tree and the graph, bottom-up through the inspector ("Used by") and focus mode. |
| Perimeter | Application views are explored. Package views and components used by the application are leaf nodes; exploring them is opt-in. |
| Integration | A `views` scope. The HTTP scope gains one hop to the rendered view, without unfolding it. |
| Entry points | Routes, controller actions, Livewire classes, Blade class components, Filament classes with a custom view, mailables and notifications. |
| Reading | Blade sources are read as text; names are resolved by the framework (view finder, `ComponentTagCompiler::componentClass()`, Livewire registry). Nothing is compiled, rendered or instantiated. |
| Storage | A `views` section on `ApplicationSnapshot`, one discovery run, one cache entry. |

## 1. Data model

### Node types

| Type | Represents | Label | Subtitle |
|---|---|---|---|
| `view` | an application Blade template (`view.paths`), Livewire 4 single and multi-file components included | view name (`shop.orders.show`) | kind: `layout`, `page`, `partial`, `component`, `livewire`, `mail` |
| `blade_component` | a Blade class component | short class name | `<x-alert>` |
| `filament_component` | a Filament class declaring a custom view | short class name | Filament kind (Page, Widget, Field, Entry, Column, Action, Component) |
| `external_view` | a package view or component used by the application, or a missing view | reference (`filament::components.button`) | package, or `missing` |
| `dynamic_view` | a view name computed at runtime | `Dynamic view` | the source expression |

Livewire classes, routes, controllers, mailables and notifications reuse their existing nodes and identifiers.

The kind of a view is inferred, first match wins: Livewire single or multi-file component → `livewire`; under `components/` → `component`; target of `@extends`, a `#[Layout]`, `->layout()` or a layout Blade component → `layout`; rendered by a mailable or notification → `mail`; rendered by a route, a controller, a Filament page or a full-page Livewire component → `page`; otherwise `partial`.

### Edge types

| Edge | From → to | Label |
|---|---|---|
| `view_extends` | view → view | `@extends` |
| `view_includes` | view → view or external | the directive (`@include`, `@includeIf`, `@includeWhen`, `@includeUnless`, `@includeFirst`, `@each`, `@component`) |
| `view_uses_component` | view → blade_component, component view or external | `<x-alert>` |
| `view_renders_livewire` | view → livewire_component, Livewire view or external | `<livewire:cart>` or `@livewire` |
| `view_references_dynamic` | view → dynamic_view | the directive |
| `renders_view` | owner → view or external | how: `render`, `layout`, `$view`, `view`, `markdown`, `text`, `Route::view` |

View edges carry `lines` (every line where the reference appears). `renders_view` edges from controllers carry `methods`, so the HTTP traversal stays action-aware.

### DTOs (`src/Domain/DTO/Views/`)

- `ViewMapData`: `views`, `owners`, `externals`, `dynamics`, all lists, empty by default.
- `ViewData`: `id`, `name`, `file`, `kind`, `references` (list of `ViewReference`), `status`, `warnings`.
- `ViewReference`: `type` (`extends`, `include`, `component`, `livewire`, `dynamic`), `written`, `directive`, `targetId` (null when unresolved), `line`.
- `ViewOwnerData`: `id`, `ownerType` (`livewire`, `blade_component`, `filament`, `mailable`, `notification`, `route`, `controller`), `class` (null for routes), `label`, `renders` (list of `RenderedView`), `status`, `warnings`.
- `RenderedView`: `targetId`, `name`, `how`, `method` (null when not tied to a method).
- `ExternalViewData`: `id`, `reference`, `package` (null), `missing`.
- `DynamicViewData`: `id`, `sourceViewId`, `directive`, `expression`, `line`.
- `ControllerActionData` gains `views` (list of view names rendered by the action), tolerated when absent.

Identifiers: `view:<name>`, `blade-component:<class>`, `filament-component:<class>`, `external-view:<reference>`, `dynamic-view:<source view name>:<line>`.

`ApplicationSnapshot::$views` defaults to an empty map and `fromArray()` accepts a missing key. `SchemaVersion` becomes `1.4`.

Deliberately out of scope: `@yield`/`@section`, `@push`/`@stack`, slots, view composers.

## 2. Discovery

Safety: no Blade compilation (custom directives and precompilers never run), no rendering, no instantiation of components, Livewire classes, Filament classes or mailables. Files are read as text, names are resolved by the framework, classes are read through reflection. Failures become warnings and `Partial` statuses.

1. **`BladeTemplateScanner`** lists the application views under `view.paths` (`resources/views/vendor/**` excluded unless `views.include_vendor_overrides`), derives their names (the Livewire 4 `⚡` marker stripped), and extracts references with their line after neutralising `{{-- --}}` comments and `@verbatim` blocks: `@extends`, the `@include` family (every candidate of `@includeFirst`), `@each` (view and empty view), `@component`, `@livewire`, `<x-…>` / `<x-ns::…>` (not `<x-slot>`), `<livewire:…>`, and `<x-dynamic-component>` / `<livewire:is>` as dynamic references. A first argument that is not a string literal is dynamic.
2. **`ViewReferenceResolver`** resolves, with memoisation: view names through the view finder (application file → `view`; package or namespaced view → `external_view`; not found → missing `external_view`); `<x-…>` through `ComponentTagCompiler::componentClass()` fed with the registered aliases and namespaces (application class → `blade_component`, other class → `external_view`, view name → resolved as a view); Livewire names through `LivewireAdapter`.
3. **`LivewireAdapter`**: Livewire 4 uses `app('livewire.finder')` (class, single-file path, multi-file directory); Livewire 3 reads the registry aliases through reflection and derives the class from `livewire.class_namespace`, without calling missing-component resolvers.
4. **`ViewOwnerDiscoverer`**:
   - Livewire classes: the literal views of `render()` and its `->layout()` call, plus the `#[Layout]` attribute read through reflection.
   - Blade class components: classes resolved from tags plus a scan of `views.blade_component_paths`; literal views of `render()`.
   - Filament classes under `views.filament_paths`: default value of a `$view` property declared by the application class, otherwise literals of `getView()`; only classes extending a Filament class.
   - Mailables and notifications: the HTTP map's dispatched ones plus a scan of `views.mail_paths`; literals of `content()`, `build()` and `toMail()` (`view:`, `markdown:`, `text:` named arguments and `->view()`, `->markdown()`, `->text()` calls).
   - Routes: `Route::view`. Controllers: the `views` of each action, found statically (`view('…')`, `View::make('…')`, `->view('…')`).
5. **`ViewMapDiscoverer`** orchestrates, after the HTTP map.

Configuration:

```php
'views' => [
    'enabled' => true,
    'include_vendor_overrides' => false,
    'explore_package_views' => false,
    'blade_component_paths' => [app_path('View/Components')],
    'filament_paths' => [app_path('Filament')],
    'mail_paths' => [app_path('Mail'), app_path('Notifications')],
    'exclude' => ['views' => []],
],
```

Every option feeds `DiscoveryContext`, hence the cache fingerprint.

## 3. Scope and interface

- **`GraphScope::Views`** seeds every application view and every owner with a `renders_view` edge, and follows `renders_view`, `view_extends`, `view_includes`, `view_uses_component`, `view_renders_livewire`, `view_references_dynamic`. Application views without incoming edge carry a `No reference found` badge.
- **HTTP bridge**: the HTTP traversal follows `renders_view` one hop from routes, controllers (action-aware), Livewire components and mailables; views stay leaves.
- **Filament and Laravel scopes** exclude the new node types; their output is unchanged.
- `views.enabled` and `DependencyGraphPlugin::allowViewsScope()` control the scope.
- Graph styles: sharp rectangle for views (thicker border for layouts), pentagon for Blade components, hexagon for Filament classes, dashed grey rectangle for externals, dashed diamond for dynamic views; `view_extends` solid, `view_includes` dashed, `view_uses_component` dotted, `renders_view` and `view_renders_livewire` coloured; labels at readable zoom.
- Tree: owners grouped (Routes and controllers, Livewire, Filament, Blade components, Mail) unfolding into their views and children, plus a group for views without a detected reference; a selected node roots the tree.
- Tables in the Views scope: Views, Components, Externals, Livewire components.
- Inspectors: view (identity, renders, used by), Blade component, Filament class, external view, dynamic view; existing Livewire, route, controller, mailable and notification inspectors list their views.
- Search matches view names and files. Translations in English and French.

## 4. Cache, exports, tests, release

- `SchemaVersion` 1.4, snapshots without `views` still read; template contents are never kept, only references.
- Mermaid labels on the view edges; `--scope=views` on the CLI commands.
- Fixtures under `tests/Fixtures/views`, unit tests for the scanner, resolver and Livewire adapter, feature tests for owners, scopes, bridge, non-regression, no instantiation and the page; a Livewire 4 single-file test skipped under Livewire 3.
- ADR 0011, README section with detection limits, CHANGELOG under 1.2.0.
- Released with the HTTP map as v1.2.0.
