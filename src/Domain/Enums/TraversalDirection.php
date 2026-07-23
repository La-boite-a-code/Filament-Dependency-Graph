<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum TraversalDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
    case Both = 'both';

    public function includesIncoming(): bool
    {
        return $this !== self::Outgoing;
    }

    public function includesOutgoing(): bool
    {
        return $this !== self::Incoming;
    }
}
