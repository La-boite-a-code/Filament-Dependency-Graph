<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Compatibility;

final class Filament4Adapter extends AbstractFilamentAdapter
{
    public function version(): int
    {
        return 4;
    }
}
