<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

/**
 * What a controller, listener or job hands over to the framework.
 */
enum DispatchKind: string
{
    case Job = 'job';
    case Mailable = 'mailable';
    case Notification = 'notification';
    case Event = 'event';

    public function nodeType(): NodeType
    {
        return match ($this) {
            self::Job => NodeType::Job,
            self::Mailable => NodeType::Mailable,
            self::Notification => NodeType::Notification,
            self::Event => NodeType::Event,
        };
    }
}
