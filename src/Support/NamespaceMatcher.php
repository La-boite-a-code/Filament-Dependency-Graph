<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Support;

final class NamespaceMatcher
{
    /**
     * Whether the class lives inside one of the given namespace prefixes.
     *
     * @param  list<string>  $namespaces
     */
    public static function matchesNamespace(string $class, array $namespaces): bool
    {
        $class = ClassName::normalize($class);

        foreach ($namespaces as $namespace) {
            $prefix = rtrim(ClassName::normalize($namespace), '\\') . '\\';

            if ($prefix === '\\') {
                continue;
            }

            if (strncasecmp($class, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the class matches one of the given class names exactly.
     *
     * @param  list<string>  $classes
     */
    public static function matchesClass(string $class, array $classes): bool
    {
        foreach ($classes as $candidate) {
            if (ClassName::equals($class, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
