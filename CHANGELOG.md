# Changelog

All notable changes to `laboiteacode/filament-dependency-graph` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
