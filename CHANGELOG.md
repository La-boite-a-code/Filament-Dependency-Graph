# Changelog

All notable changes to `laboiteacode/filament-dependency-graph` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-09-23

### Added

- Add an HTTP scope that maps application routes to their controller actions, form requests, route-bound and referenced models, model policies, events and listeners, and the jobs, mailables and notifications dispatched along the way.
- Discover routes from the router (route cache included), listeners from the event dispatcher (event discovery included) and policies through registered policies, the `UsePolicy` attribute and the gate's naming convention, without instantiating controllers, form requests or policies.
- Detect dispatches and model references statically in controller actions, listeners and job `handle()` methods, following injected application classes one level deep, with `file:line` locations and the traversed classes on every edge.
- Read form request rules on demand with the opt-in `http.form_request_rules` option.
- Add HTTP inspectors for routes, controllers (action by action), form requests, policies, events, listeners and dispatched classes, plus an HTTP usage section on models and details on HTTP edges.
- Add routes, events and dispatches table datasets, an action-aware route tree grouped by URI segment with a group for events without a detected dispatcher, and a `No dispatcher found` badge on those events.
- Resolve route middleware from the route definition and the framework controller attributes the way the router does (aliases, nested groups, exclusions), and filter the HTTP scope by middleware from the explorer, `GraphQuery::$middleware` or `--middleware` on `filament-dependency-graph:export`.
- Add the `http` configuration block and the `allowHttpScope()` plugin method.
- Style the new node types in the graph from the Filament palette and label controller methods and dispatch kinds in the graph and in Mermaid exports.

### Changed

- Extract the token based source scanner shared by Livewire and HTTP discovery; Livewire discovery output is unchanged.
- Bump the snapshot schema to `1.3` for the HTTP map section; cached snapshots are rebuilt automatically.

## [1.1.2] - 2026-09-22

### Fixed

- Rebuild the published frontend bundle with cytoscape 3.34.3, cytoscape-dagre 4.0.1 and esbuild 0.28.2 so `dist/` matches the declared npm dependencies.
- Rewrite the cycle detector without a by-reference recursive closure so static analysis infers its component types correctly on PHPStan 2.2.

### Changed

- Rebuild and commit `dist/` automatically on Dependabot npm pull requests, then run the checks against the rebuilt commit.
- Allow every CI workflow to be started manually and stop persisting Git credentials in read-only checkouts.

## [1.1.1] - 2026-07-29

### Fixed

- Keep discovery and every table dataset available when an Eloquent model disables its primary key with `false`; absent primary keys are now represented by `null`, and unexpected model metadata failures are isolated as partial records.
- Bump the snapshot schema to `1.2` so cached discovery data is rebuilt after the nullable primary-key fix.

## [1.1.0] - 2026-07-28

### Added

- Discover application Livewire components outside Filament panels in the Laravel scope, including conventional aliases, rendered views, public APIs and model dependencies inferred without component instantiation.
- Add Livewire component nodes, component-to-model edges, search/filter support, a dedicated inspector and a fourth table view.
- Add configurable Livewire paths and namespaces, with fluent plugin registration methods.

### Changed

- Give the graph more usable space with an adaptive-height canvas, a native Filament slide-over inspector and an expandable canvas mode.
- Align the workspace, explorer and graph controls with the cover design while continuing to derive every color from Filament theme variables.
- Replace the handcrafted inventory tables with a native searchable, sortable and paginated Filament Table organized by category.
- Add explicit zoom controls and improve node, edge and selection contrast.
- Bump the deterministic serialization schema to `1.1` for Livewire component snapshot data.

### Fixed

- Use Filament's native copyable text behavior for inspector values, including its tooltip and confirmation feedback.

### Security

- Update esbuild to a release that fixes `GHSA-67mh-4wv8-2f99`.
- Pin every GitHub Action to an immutable commit, restrict workflow token permissions and configure Dependabot for Composer, npm and GitHub Actions with a seven-day update cooldown.

## [1.0.0] - 2026-07-24

First stable release.

### Added

- Automatic discovery of Eloquent models, relations, Filament panels, resources, pages and relation managers.
- Support for all Eloquent relation types, including polymorphic relations and morph maps.
- Deterministic graph domain model with focus traversal, cycle detection, orphan detection and shortest path search.
- Filament page with interactive graph view, tree view, table view, contextual inspector, search and focus mode.
- Readable graph rendering: Cytoscape.js with a layered dagre layout for the hierarchical mode and fCoSE for the force-directed mode, relation labels that only render at readable zoom levels, selection that fades everything outside its neighbourhood, and reliable sizing when the component mounts inside a hidden or unmeasured container.
- Native Filament UI: sections, tabs, buttons, selects, checkboxes and badges, with a graph palette and CSS tokens that follow the panel theme color scales in light and dark mode.
- Filament scope and Laravel scope with panel, node type, relation type, namespace, ownership and orphan filters.
- Navigation and page customization, fluently on the plugin or via configuration: `navigationLabel()`, `navigationIcon()`, `activeNavigationIcon()`, `navigationGroup()`, `navigationSort()`, `navigationParentItem()`, `navigationBadge()`, `registerNavigation()`, `slug()`, `cluster()`, `maxContentWidth()` and `canAccessUsing()`.
- Full-width page by default, configurable through `page.max_content_width` or `maxContentWidth()`.
- JSON and Mermaid exporters with a stable schema, available from the UI and the CLI, plus custom exporter and inspector registration.
- Snapshot cache with `filament-dependency-graph:cache` and `filament-dependency-graph:clear` commands.
- `filament-dependency-graph:export` command.
- Programmatic API through the `DependencyGraph` facade and the `DependencyGraphManager` contract.
- Compatibility adapters for Filament 4.x and 5.x.
- Local-only visibility by default with a configurable visibility callback.
- English and French translations.

[Unreleased]: https://github.com/La-boite-a-code/Filament-Dependency-Graph/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/La-boite-a-code/Filament-Dependency-Graph/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/La-boite-a-code/Filament-Dependency-Graph/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/La-boite-a-code/Filament-Dependency-Graph/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/La-boite-a-code/Filament-Dependency-Graph/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/La-boite-a-code/Filament-Dependency-Graph/releases/tag/v1.0.0
