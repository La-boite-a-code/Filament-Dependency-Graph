<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum NodeType: string
{
    case Panel = 'panel';
    case Resource = 'resource';
    case LivewireComponent = 'livewire_component';
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
            self::LivewireComponent => 2,
            self::Model => 3,
            self::PolymorphicTarget => 4,
        };
    }
}
