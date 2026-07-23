# Changelog

All notable changes to `laboiteacode/filament-dependency-graph` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
