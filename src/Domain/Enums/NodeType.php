<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum NodeType: string
{
    case Panel = 'panel';
    case Resource = 'resource';
    case Model = 'model';
    case PolymorphicTarget = 'polymorphic_target';

    /**
     * Lower values are ordered first when sorting nodes deterministically.
     */
    public function sortPriority(): int
    {
        return match ($this) {
            self::Panel => 0,
            self::Resource => 1,
            self::Model => 2,
            self::PolymorphicTarget => 3,
        };
    }
}
