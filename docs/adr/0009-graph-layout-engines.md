# ADR 0009: Dagre and fCoSE as graph layout engines

## Status

Accepted. Amends the layout detail of ADR 0001.

## Context

The first release used the layouts bundled with Cytoscape.js: `breadthfirst` for the hierarchical mode and `cose` for the force-directed mode. On realistic applications (tens of models, close to a hundred edges) `breadthfirst` assigns ranks by BFS level only: every model lands in one or two wide rows, edges sweep diagonally across the whole canvas and cross each other, and edge labels pile up into an unreadable band. `cose` produces loose clusters with frequent node overlaps.

## Decision

Register two dedicated layout extensions, bundled the same way as Cytoscape itself:

- `cytoscape-dagre` for the hierarchical mode. Dagre implements a Sugiyama-style layered layout (network-simplex ranking, crossing minimisation), which is the standard for dependency graphs: edges flow between adjacent layers and related nodes end up side by side.
- `cytoscape-fcose` for the force-directed mode, with `packComponents` so disconnected sub-graphs and orphan models are packed next to the main component instead of drifting over it.

Alongside the engines, three readability rules live in the renderer:

- Relation labels only render once the on-screen font size reaches a readable threshold (`min-zoomed-font-size`), on a background chip; the zoomed-out overview stays clean.
- Selecting a node or an edge fades everything outside its direct neighbourhood (focus + context) instead of only recolouring the selection.
- Node widths are computed from the label with an offscreen canvas measurement instead of the deprecated `width: 'label'` style, whose lazy renderer measurement leaves nodes invisible when the component mounts inside a container that has no dimensions yet (lazy Alpine mount, hidden tab). The initial fit re-runs from a `ResizeObserver` for the same reason.

## Consequences

- The lazily loaded bundle grows from roughly 450 KB to roughly 620 KB minified; still loaded only on the dependency graph page.
- Layout options remain plain data in `layoutOptions()`; swapping or tuning an engine stays a one-place change.
- `spacingFactor`-style global scaling is replaced by explicit `nodeSep`/`rankSep` (dagre) and `idealEdgeLength`/`nodeRepulsion` (fcose) knobs.
