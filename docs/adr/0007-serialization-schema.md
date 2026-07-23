# ADR 0007: Versioned, deterministic graph serialization schema

## Status

Accepted

## Context

The graph JSON is consumed by the frontend renderer, the JSON export, snapshot tests and future tooling. Consumers need a stable contract and a way to detect incompatible changes.

## Decision

Nodes serialize as `{id, type, label, subtitle, metadata, badges, status}` and edges as `{id, source, target, type, label, metadata, status}`, wrapped in `{nodes, edges}`. The JSON export adds `schemaVersion`, `generatedAt`, `environment`, `scope`, `filters` and `warnings` on top. `SchemaVersion::CURRENT` names the schema and participates in the cache key.

Determinism rules: nodes sort by type priority, namespace, label then id; edges sort by source, target, type then label; identifiers are normalized lowercase dotted paths with no random parts; metadata keys are emitted in fixed order.

Exports never contain secrets, environment variables, credentials or record data.

## Consequences

- Two runs on the same application produce byte-identical exports, enabling snapshot tests and meaningful diffs between versions.
- Any breaking change to the shape requires a `SchemaVersion` bump, which also invalidates snapshot caches.
- The frontend consumes exactly the exported shape, so there is one serializer to maintain.
