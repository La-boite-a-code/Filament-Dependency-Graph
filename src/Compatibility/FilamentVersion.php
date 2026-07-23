<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Compatibility;

use Composer\InstalledVersions;
use Throwable;

final class FilamentVersion
{
    /**
     * Detects the installed Filament major version, or null when Filament
     * cannot be found in the Composer runtime metadata.
     */
    public static function detect(): ?int
    {
        $version = self::full();

        return $version === null ? null : (int) explode('.', $version)[0];
    }

    /**
     * Full installed Filament version string, or null when unavailable.
     */
    public static function full(): ?string
    {
        foreach (['filament/support', 'filament/filament'] as $package) {
            try {
                if (! InstalledVersions::isInstalled($package)) {
                    continue;
                }

                $version = InstalledVersions::getVersion($package);

                if ($version === null) {
                    continue;
                }

                return $version;
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
