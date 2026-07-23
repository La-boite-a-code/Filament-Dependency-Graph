<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Compatibility;

use Filament\Panel;

/**
 * Isolates every Filament API call whose surface may differ between the
 * supported major versions. Discoverers only talk to this adapter, so
 * version-specific behavior never leaks into the rest of the codebase.
 */
interface FilamentAdapter
{
    public function version(): int;

    /**
     * @return array<string, Panel>
     */
    public function panels(): array;

    /**
     * The panel handling the current request, or the default panel.
     */
    public function currentPanel(): ?Panel;

    /**
     * @param  class-string  $resource
     */
    public function resourceModel(string $resource): string;

    /**
     * @param  class-string  $resource
     */
    public function resourceLabel(string $resource): string;

    /**
     * @param  class-string  $resource
     */
    public function resourcePluralLabel(string $resource): string;

    /**
     * @param  class-string  $resource
     */
    public function resourceNavigationGroup(string $resource): ?string;

    /**
     * @param  class-string  $resource
     */
    public function resourceNavigationIcon(string $resource): ?string;

    /**
     * @param  class-string  $resource
     * @return array<string, string> Route key to page class.
     */
    public function resourcePages(string $resource): array;

    /**
     * @param  class-string  $resource
     * @return list<class-string>
     */
    public function resourceRelationManagers(string $resource): array;

    /**
     * @param  class-string  $relationManager
     */
    public function relationManagerRelationship(string $relationManager): ?string;

    /**
     * @param  class-string  $relationManager
     */
    public function relationManagerRelatedResource(string $relationManager): ?string;

    /**
     * @param  class-string  $relationManager
     */
    public function relationManagerTitle(string $relationManager): ?string;
}
