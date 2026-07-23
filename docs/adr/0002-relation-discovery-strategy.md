# ADR 0002: Layered relation discovery with guarded invocation

## Status

Accepted

## Context

Relations are declared as ordinary methods on Eloquent models. There is no registry to read, so discovery must inspect code. Executing arbitrary methods risks side effects; static analysis alone cannot extract keys, pivot tables or morph metadata.

## Decision

Detection is layered, and only detected candidates are ever invoked:

1. **Native return types** (strategy A): public, non-static, zero-required-argument methods whose return type is a known `Relation` subclass.
2. **Docblock return types** (strategy B, enabled by default): when no native type exists, a `@return` tag naming a relation type marks the method as a candidate.
3. **Heuristic invocation** (strategy C, disabled by default): remaining untyped methods are invoked and kept only when they return a `Relation`. It stays opt-in because invoking arbitrary getters can trigger application side effects.

Candidates are invoked inside a `try`/`catch (Throwable)` on a memoized model instance. The relation object is inspected without executing its query. A throwing method produces partial relation data with a warning instead of aborting discovery.

Methods that are static, require parameters, are declared inside `Illuminate\`, look like accessors or scopes, or are excluded by configuration are never candidates.

## Consequences

- Typed applications get complete metadata with zero configuration.
- Discovery never crashes the page: failures degrade to warnings surfaced in the inspector and diagnostics.
- `morphedByMany` cannot be distinguished from `morphToMany` by name alone; the invocation result (`MorphToMany::getInverse()`) disambiguates.
