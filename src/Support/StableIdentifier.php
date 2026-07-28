<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Support;

use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;

/**
 * Deterministic identifier generation.
 *
 * Identifiers never contain random parts and stay stable across cache
 * rebuilds, which makes snapshots, exports and diffs comparable over time.
 */
final class StableIdentifier
{
    public static function model(string $class): string
    {
        return 'model:' . self::normalizeClass($class);
    }

    public static function resource(string $class): string
    {
        return 'resource:' . self::normalizeClass($class);
    }

    public static function livewireComponent(string $class): string
    {
        return 'livewire:' . self::normalizeClass($class);
    }

    public static function panel(string $panelId): string
    {
        return 'panel:' . self::normalizeSegment($panelId);
    }

    /**
     * The relation method keeps its exact case so identifiers remain readable
     * and unambiguous when two methods differ only by case.
     */
    public static function relation(string $sourceClass, string $method): string
    {
        return 'relation:' . self::normalizeClass($sourceClass) . ':' . $method;
    }

    public static function polymorphicTarget(string $sourceClass, string $method): string
    {
        return 'polymorphic:' . self::normalizeClass($sourceClass) . ':' . $method;
    }

    public static function edge(EdgeType $type, string $sourceId, string $targetId, ?string $discriminator = null): string
    {
        $identifier = 'edge:' . $type->value . ':' . $sourceId . ':' . $targetId;

        if ($discriminator !== null && $discriminator !== '') {
            $identifier .= ':' . $discriminator;
        }

        return $identifier;
    }

    /**
     * Normalizes a fully qualified class name into a lowercase dotted path.
     *
     * Example: "App\Filament\Resources\OrderResource" becomes
     * "app.filament.resources.order-resource".
     */
    public static function normalizeClass(string $class): string
    {
        $segments = explode('\\', ClassName::normalize($class));

        $segments = array_map(
            static fn (string $segment): string => self::kebab($segment),
            $segments,
        );

        $normalized = implode('.', array_filter($segments, static fn (string $segment): bool => $segment !== ''));

        return self::collapseSeparators($normalized);
    }

    private static function normalizeSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9._-]+/', '-', $value);

        return self::collapseSeparators($value);
    }

    private static function kebab(string $segment): string
    {
        $segment = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $segment);
        $segment = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $segment);
        $segment = strtolower($segment);
        $segment = (string) preg_replace('/[^a-z0-9._-]+/', '-', $segment);

        return trim($segment, '-');
    }

    private static function collapseSeparators(string $value): string
    {
        $value = (string) preg_replace('/\.{2,}/', '.', $value);
        $value = (string) preg_replace('/-{2,}/', '-', $value);

        return trim($value, '.-');
    }
}
