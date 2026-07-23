<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Compatibility;

final class Filament5Adapter extends AbstractFilamentAdapter
{
    public function version(): int
    {
        return 5;
    }
}
