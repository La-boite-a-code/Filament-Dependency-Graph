# ADR 0005: One adapter interface for Filament 4 and 5

## Status

Accepted

## Context

The package supports Filament 4.x and 5.x. Scattered version checks through the codebase would rot quickly and make version bumps risky.

## Decision

Every Filament API call whose surface could differ between majors goes through the `Compatibility\FilamentAdapter` interface: panel registry access, current panel resolution, resource metadata, pages, relation managers, and enum or icon normalization. `FilamentVersion::detect()` reads the installed version from Composer runtime metadata, and the service provider binds `Filament4Adapter` or `Filament5Adapter`. Booting without a supported major throws a clear configuration exception, matching the documented fatal conditions.

The APIs used are currently identical across both majors, so both adapters share `AbstractFilamentAdapter`. The structure exists so the first real divergence lands in one file.

## Consequences

- Discoverers and the UI never mention a Filament version.
- The CI matrix runs both majors; a divergence surfaces as a failing job and a targeted adapter override.
- Dropping or adding a major is localized to the compatibility namespace and the service provider binding.
