# ADR 0008: URL query string for shareable state, Livewire for the rest

## Status

Accepted

## Context

Focus state must be shareable between developers (spec requirement), the selected view should survive reloads, and inspector-only interactions must not re-render the whole graph.

## Decision

- Shareable state lives in the URL through Livewire `#[Url]` bindings: scope, panels, active view, focus node, depth and direction. A copied URL such as `/admin/dependency-graph?focus=model:app.models.order&depth=2&direction=both` reproduces the same workspace.
- Transient state (selection, search text, explorer filters) stays in Livewire component state only.
- The graph canvas re-initializes only when its payload hash changes (`wire:key` on the payload), so selecting a node updates the inspector without rebuilding the Cytoscape instance; centering on search results flows through a browser event.

Server-side per-user persistence of the preferred view is deferred; the URL already keeps the view on reload and can be bookmarked.

## Consequences

- Deep links are the collaboration primitive: no server storage, nothing to migrate.
- Browser navigation naturally steps through focus changes.
- If per-user persistence lands later, it becomes a decorator around the same URL-backed properties.
