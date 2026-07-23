# Contributing

Thank you for considering a contribution to Filament Dependency Graph.

## Development setup

```bash
git clone https://github.com/la-boite-a-code/filament-dependency-graph.git
cd filament-dependency-graph
composer install
npm install
```

Frontend assets are built into `dist/` with:

```bash
npm run build
```

## Quality gates

Every pull request must pass the same checks as CI:

```bash
composer validate --strict
composer test
composer analyse
composer lint
```

- Tests run on Pest against the Testbench fixture application in `tests/Fixtures`.
- Static analysis runs on PHPStan with Larastan at level 8.
- Code style follows Laravel Pint with the repository preset; run `composer format` before committing.

## Pull requests

Each pull request should include:

- a concise problem statement;
- a summary of the implementation;
- tests added or updated;
- screenshots for UI changes;
- compatibility impact (PHP, Laravel, Filament versions);
- migration impact when applicable.

Keep commits small and imperative, describing the code change only, for example:

```text
Add Eloquent relation discovery
Fix morph relation target resolution
Refactor graph node serialization
```

## Design rules

- Discovery stays read-only: never modify application files, records or schema.
- One broken model must never abort global discovery; record a warning instead.
- The domain layer (`src/Domain`) must not depend on Filament or Livewire.
- Output must remain deterministic: stable identifiers, stable ordering.
- Filament version differences belong in `src/Compatibility` adapters, never inline.
- Every feature ships with tests and documentation.
