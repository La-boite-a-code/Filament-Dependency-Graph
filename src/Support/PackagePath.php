<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Support;

final class PackagePath
{
    /**
     * Whether a file path is located inside the given root directory.
     */
    public static function isInside(string $path, string $root): bool
    {
        if ($path === '' || $root === '') {
            return false;
        }

        $path = self::normalize($path);
        $root = rtrim(self::normalize($root), '/') . '/';

        return strncmp($path, $root, strlen($root)) === 0;
    }

    /**
     * A file is application-owned when it lives under the project base path
     * and outside the vendor directory.
     */
    public static function isApplicationOwned(string $path, string $basePath, string $vendorPath): bool
    {
        if (! self::isInside($path, $basePath)) {
            return false;
        }

        return $vendorPath === '' || ! self::isInside($path, $vendorPath);
    }

    public static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
