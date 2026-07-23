# ADR 0001: Cytoscape.js as the graph renderer

## Status

Accepted

## Context

The interactive graph view needs zoom, pan, node dragging, fit-to-view, centering, typed nodes, edge labels, selection events, dark mode and comfortable handling of several hundred nodes. Candidates considered: Cytoscape.js, Sigma.js, React Flow behind a build step, and a custom canvas renderer.

## Decision

Use Cytoscape.js, bundled with esbuild into `dist/components/dependency-graph.js` and loaded lazily as a Filament Alpine component.

- It ships graph layouts (breadthfirst for the hierarchical mode, cose for the force-directed mode) and graph algorithms out of the box.
- It handles thousands of elements with a plain dependency-free bundle, no React runtime required inside Filament.
- Styling is data-driven, which maps directly to our typed nodes and lets us restyle on theme changes.

## Consequences

- The domain layer stays renderer-agnostic: the backend produces normalized graph JSON, and any future renderer can consume the same payload.
- The bundled asset weighs around 440 KB minified; it is only loaded on the dependency graph page, asynchronously.
- Layout switching re-runs a Cytoscape layout instead of re-rendering Livewire, keeping inspector-only updates cheap.
