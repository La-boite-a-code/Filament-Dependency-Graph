<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum GraphScope: string
{
    case Filament = 'filament';
    case Laravel = 'laravel';
}
