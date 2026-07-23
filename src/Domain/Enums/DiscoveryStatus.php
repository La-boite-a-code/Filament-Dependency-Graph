<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum DiscoveryStatus: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case Failed = 'failed';

    public function isPartial(): bool
    {
        return $this !== self::Complete;
    }
}
