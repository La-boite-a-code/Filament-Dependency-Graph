# ADR 0006: One placeholder node per morphTo relation

## Status

Accepted

## Context

A `morphTo` relation has no single concrete target: the target class lives in a type column and possibly in the morph map. Options considered: one edge per known morph map target, one shared "polymorphic" node, or one placeholder node per relation.

## Decision

For V1, every `morphTo` relation gets its own placeholder node of type `polymorphic_target`, labeled after the relation method (for example `Commentable`), with the morph type column stored as metadata. The relation edge points from the source model to this placeholder. The relation record keeps `targetModelId` null and carries a warning explaining that the target cannot be resolved to a single model.

## Consequences

- The graph stays readable: `Comment` points at one `Commentable` placeholder instead of fanning out to every possible model.
- Identifiers stay deterministic (`polymorphic:{source}:{method}`), so exports and diffs remain stable.
- The domain model does not prevent a future expansion into per-target logical edges driven by the morph map; that work only touches the graph builder.
