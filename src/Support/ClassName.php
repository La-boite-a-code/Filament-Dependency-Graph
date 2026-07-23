<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Support;

final class ClassName
{
    public static function normalize(string $class): string
    {
        return ltrim($class, '\\');
    }

    public static function shortName(string $class): string
    {
        $class = self::normalize($class);
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    public static function namespace(string $class): string
    {
        $class = self::normalize($class);
        $position = strrpos($class, '\\');

        return $position === false ? '' : substr($class, 0, $position);
    }

    public static function equals(string $first, string $second): bool
    {
        return strcasecmp(self::normalize($first), self::normalize($second)) === 0;
    }
}
