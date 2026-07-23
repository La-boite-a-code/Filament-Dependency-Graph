# ADR 0003: Final readonly DTOs with array serialization

## Status

Accepted

## Context

Snapshots must be cacheable, exportable, diffable and safe to hand to extension hooks. Mutable data would make deterministic output and cache integrity hard to guarantee.

## Decision

Every domain DTO and value object is a `final readonly class`. No DTO contains a service, closure, model instance, resource instance or container reference. Each structural DTO exposes `toArray()` and `fromArray()` with snake_case keys, providing one canonical serialized shape used by the cache, the fingerprint and the exports. Enums back every closed set of values.

Immutability is enforced by architecture tests.

## Consequences

- Cached payloads survive class autoload changes better than native PHP serialization, and corrupted payloads simply fail `fromArray()` and trigger rediscovery.
- Derived values (badges, inverse flags) are produced by rebuilding objects through withers, never by mutation.
- Adding a field is a schema change: bump `SchemaVersion::CURRENT`, which invalidates caches automatically.
