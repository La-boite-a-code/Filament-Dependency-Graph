<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable as DispatchableJob;
use Illuminate\Foundation\Events\Dispatchable as DispatchableEvent;
use Illuminate\Notifications\Notification;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use Throwable;

/**
 * Tells what a class is once handed over to the framework, from its type
 * hierarchy only. Classes that are none of these are not dispatches.
 */
final class DispatchClassifier
{
    /**
     * Static methods that hand a job or an event over to the framework.
     */
    public const DISPATCH_METHODS = [
        'dispatch',
        'dispatchsync',
        'dispatchnow',
        'dispatchif',
        'dispatchunless',
        'dispatchafterresponse',
        'broadcast',
        'withchain',
    ];

    /** @var array<string, DispatchKind|null> */
    private array $kinds = [];

    /**
     * @param  array<string, true>  $eventClasses  Events known to the dispatcher.
     */
    public function classify(string $class, array $eventClasses = []): ?DispatchKind
    {
        if (isset($eventClasses[$class])) {
            return DispatchKind::Event;
        }

        if (array_key_exists($class, $this->kinds)) {
            return $this->kinds[$class];
        }

        return $this->kinds[$class] = $this->resolve($class);
    }

    public function isDispatchMethod(string $method): bool
    {
        return in_array(strtolower($method), self::DISPATCH_METHODS, true);
    }

    public function isQueued(string $class): bool
    {
        try {
            return is_a($class, ShouldQueue::class, true);
        } catch (Throwable) {
            return false;
        }
    }

    private function resolve(string $class): ?DispatchKind
    {
        try {
            if (! class_exists($class)) {
                return null;
            }

            $traits = class_uses_recursive($class);

            return match (true) {
                is_a($class, Mailable::class, true) => DispatchKind::Mailable,
                is_a($class, Notification::class, true) => DispatchKind::Notification,
                isset($traits[DispatchableEvent::class]),
                is_a($class, ShouldBroadcast::class, true),
                is_a($class, ShouldDispatchAfterCommit::class, true) => DispatchKind::Event,
                isset($traits[DispatchableJob::class]),
                is_a($class, ShouldQueue::class, true) => DispatchKind::Job,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
