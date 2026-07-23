# ADR 0004: Configuration-fingerprint cache keys, no file fingerprinting in V1

## Status

Accepted

## Context

Reflection and file scanning are too expensive to run on every request, so snapshots are cached. Automatic invalidation based on every source file modification time would cost almost as much as discovery itself.

## Decision

The cache stores the `ApplicationSnapshot` as its serialized array, never rendered output. The key is a hash of every input that can change discovery output: the full discovery context, the application environment, the Laravel, Filament and PHP versions, and the serialization schema version.

Invalidation is explicit: a TTL (3600 seconds by default), the `filament-dependency-graph:cache` and `filament-dependency-graph:clear` commands. The cache is bypassed in the testing environment. A key index is maintained so flushing removes only package entries. File fingerprinting stays out of V1 and can be added later behind the same `GraphCache` contract.

## Consequences

- Code changes within an unchanged configuration are only picked up after the TTL or an explicit rebuild; the README documents this.
- Corrupted cache entries deserialize into a miss, are forgotten, and discovery runs again.
- Any configuration or version change produces a different key, so stale graphs are never served across upgrades.
