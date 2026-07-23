# Changelog

All notable changes to `laboiteacode/filament-dependency-graph` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Navigation and page customization, fluently on the plugin or via configuration: `navigationLabel()`, `navigationIcon()`, `activeNavigationIcon()`, `navigationGroup()`, `navigationSort()`, `navigationParentItem()`, `navigationBadge()`, `registerNavigation()`, `slug()`, `cluster()`, `maxContentWidth()` and `canAccessUsing()`.
- The page renders full width by default (configurable through `page.max_content_width` or `maxContentWidth()`).

### Changed

- The graph palette now follows the panel theme color scales (`primary`, `success`, `info`, `warning`, `gray`) with the previous colors as fallback, and the whole page chrome (toolbar, explorer, inspector, tree and table cards, badges, tokens) is built from the native Filament section and badge components and the theme CSS variables.

- Hierarchical graph layout now uses dagre (layered Sugiyama layout) and the force-directed layout uses fCoSE, replacing the built-in `breadthfirst` and `cose` layouts for much more readable graphs.
- Relation labels on edges only appear at readable zoom levels, on a background chip.
- Selecting a node or an edge fades everything outside its direct neighbourhood.
- Explorer and toolbar controls (checkboxes, selects, text inputs, buttons, tabs) now use the native Filament Blade components instead of custom-styled inputs.

### Fixed

- The graph now sizes and fits reliably when the component mounts inside a container that is still hidden or unmeasured, and pointer hit-testing stays accurate after the inspector opens or closes.

## [0.1.0] - 2026-07-23

### Added

- Automatic discovery of Eloquent models, relations, Filament panels, resources, pages and relation managers.
- Support for all Eloquent relation types, including polymorphic relations and morph maps.
- Deterministic graph domain model with focus traversal, cycle detection, orphan detection and shortest path search.
- Filament page with interactive graph view (Cytoscape.js), tree view, table view, contextual inspector, search and focus mode.
- Filament scope and Laravel scope with panel, node type, relation type, namespace, ownership and orphan filters.
- JSON and Mermaid exporters with a stable schema, available from the UI and the CLI.
- Snapshot cache with `filament-dependency-graph:cache` and `filament-dependency-graph:clear` commands.
- `filament-dependency-graph:export` command.
- Programmatic API through the `DependencyGraph` facade and the `DependencyGraphManager` contract.
- Compatibility adapters for Filament 4.x and 5.x.
- Local-only visibility by default with a configurable visibility callback.
