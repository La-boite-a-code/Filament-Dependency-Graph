<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use ReflectionMethod;

/**
 * Detects relation return declarations in PHPDoc blocks, used when a method
 * has no native return type.
 */
final class DocblockRelationParser
{
    /**
     * @return array{type: RelationType, target: string|null}|null
     */
    public static function parse(ReflectionMethod $method): ?array
    {
        $docblock = $method->getDocComment();

        if ($docblock === false) {
            return null;
        }

        if (preg_match('/@return\s+(.+)$/m', $docblock, $matches) !== 1) {
            return null;
        }

        $declaration = trim($matches[1]);

        if (str_ends_with($declaration, '*/')) {
            $declaration = trim(substr($declaration, 0, -2));
        }

        foreach (explode('|', $declaration) as $candidate) {
            $candidate = ltrim(trim($candidate), '?');
            $target = null;

            if (preg_match('/^([^<]+)<(.+)>$/', $candidate, $generic) === 1) {
                $candidate = $generic[1];
                $target = self::firstGenericArgument($generic[2]);
            }

            $type = RelationTypeMap::fromName(trim($candidate, '\\'));

            if ($type !== null) {
                return ['type' => $type, 'target' => $target];
            }
        }

        return null;
    }

    /**
     * The first template argument of a relation generic is the related model.
     * Only fully qualified names are trusted, because resolving import
     * aliases would require parsing the whole file.
     */
    private static function firstGenericArgument(string $arguments): ?string
    {
        $first = trim(explode(',', $arguments)[0]);

        if (! str_starts_with($first, '\\')) {
            return null;
        }

        $class = ltrim($first, '\\');

        return $class === '' ? null : $class;
    }
}
