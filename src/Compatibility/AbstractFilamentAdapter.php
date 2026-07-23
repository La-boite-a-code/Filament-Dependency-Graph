<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Compatibility;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use ReflectionClass;
use Throwable;
use UnitEnum;

/**
 * Shared implementation for the Filament majors currently supported. The
 * APIs used here are identical in Filament 4 and 5; version-specific
 * overrides belong in the concrete adapters.
 */
abstract class AbstractFilamentAdapter implements FilamentAdapter
{
    public function panels(): array
    {
        return Filament::getPanels();
    }

    public function currentPanel(): ?Panel
    {
        $manager = Filament::getFacadeRoot();

        if (is_object($manager) && method_exists($manager, 'getCurrentOrDefaultPanel')) {
            return $manager->getCurrentOrDefaultPanel();
        }

        return Filament::getCurrentPanel();
    }

    public function resourceModel(string $resource): string
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        return $resource::getModel();
    }

    public function resourceLabel(string $resource): string
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        return $resource::getModelLabel();
    }

    public function resourcePluralLabel(string $resource): string
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        return $resource::getPluralModelLabel();
    }

    public function resourceNavigationGroup(string $resource): ?string
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        return $this->normalizeEnumOrString($resource::getNavigationGroup());
    }

    public function resourceNavigationIcon(string $resource): ?string
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        $icon = $resource::getNavigationIcon();

        if ($icon instanceof Htmlable) {
            return null;
        }

        return $this->normalizeEnumOrString($icon);
    }

    public function resourcePages(string $resource): array
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        $pages = [];

        foreach ($resource::getPages() as $routeKey => $registration) {
            $pages[(string) $routeKey] = $registration->getPage();
        }

        return $pages;
    }

    public function resourceRelationManagers(string $resource): array
    {
        /** @var class-string<\Filament\Resources\Resource> $resource */
        return $this->flattenRelationManagers($resource::getRelations());
    }

    public function relationManagerRelationship(string $relationManager): ?string
    {
        try {
            if (method_exists($relationManager, 'getRelationshipName')) {
                return $relationManager::getRelationshipName();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function relationManagerRelatedResource(string $relationManager): ?string
    {
        try {
            if (method_exists($relationManager, 'getRelatedResource')) {
                return $relationManager::getRelatedResource();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function relationManagerTitle(string $relationManager): ?string
    {
        try {
            $properties = (new ReflectionClass($relationManager))->getStaticProperties();

            $title = $properties['title'] ?? null;

            return is_string($title) ? $title : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<array-key, mixed>  $relations
     * @return list<class-string>
     */
    protected function flattenRelationManagers(array $relations): array
    {
        $managers = [];

        foreach ($relations as $relation) {
            if (is_string($relation)) {
                /** @var class-string $relation */
                $managers[] = $relation;

                continue;
            }

            if ($relation instanceof RelationManagerConfiguration) {
                /** @var class-string $managerClass */
                $managerClass = $relation->relationManager;
                $managers[] = $managerClass;

                continue;
            }

            if ($relation instanceof RelationGroup) {
                $managers = [...$managers, ...$this->flattenRelationManagers($relation->getManagers())];
            }
        }

        return array_values(array_unique($managers));
    }

    protected function normalizeEnumOrString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return is_string($value) ? $value : null;
    }
}
