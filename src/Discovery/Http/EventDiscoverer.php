<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\EventData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ListenerData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Reads the event dispatcher's listener map, which also contains the
 * listeners registered by Laravel's event discovery.
 */
final class EventDiscoverer implements CollectsDiscoveryWarnings
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly MethodInspector $inspector,
        private readonly DispatchClassifier $classifier,
    ) {}

    /**
     * Class based events known to the dispatcher, used to classify
     * dispatched classes.
     *
     * @return array<string, true>
     */
    public function eventClasses(): array
    {
        $classes = [];

        foreach (array_keys($this->rawListeners()) as $event) {
            if ($this->isClassEvent($event)) {
                $classes[$event] = true;
            }
        }

        return $classes;
    }

    /**
     * @param  array<string, true>  $eventClasses
     * @return array{0: list<EventData>, 1: list<ListenerData>}
     */
    public function discover(DiscoveryContext $context, array $eventClasses): array
    {
        /** @var array<string, array{listeners: list<string>, closures: int}> $events */
        $events = [];
        /** @var array<string, array<string, string>> $listeners Listener class to event to method. */
        $listeners = [];

        foreach ($this->rawListeners() as $event => $registered) {
            if (! $this->isClassEvent($event)) {
                continue;
            }

            $keptListeners = [];
            $closures = 0;

            foreach ($registered as $listener) {
                $callable = $this->callable($listener);

                if ($callable === null) {
                    $closures++;

                    continue;
                }

                [$class, $method] = $callable;

                if (! NamespaceMatcher::matchesNamespace($class, $context->httpApplicationNamespaces)) {
                    continue;
                }

                $keptListeners[] = $class;
                $listeners[$class][$event] = $method;
            }

            if ($keptListeners === [] && ! NamespaceMatcher::matchesNamespace($event, $context->httpApplicationNamespaces)) {
                continue;
            }

            $keptListeners = array_values(array_unique($keptListeners));
            sort($keptListeners, SORT_STRING);

            $events[$event] = ['listeners' => $keptListeners, 'closures' => $closures];
        }

        $eventData = [];

        foreach ($events as $class => $event) {
            $eventData[] = new EventData(
                id: StableIdentifier::event($class),
                class: $class,
                file: $this->file($class, $context),
                listenerClasses: $event['listeners'],
                closureListenerCount: $event['closures'],
                status: DiscoveryStatus::Complete,
                warnings: [],
            );
        }

        $listenerData = [];

        foreach ($listeners as $class => $handled) {
            ksort($handled, SORT_STRING);
            $listenerData[] = $this->listener($class, $handled, $context, $eventClasses);
        }

        usort($eventData, static fn (EventData $a, EventData $b): int => strcmp($a->id, $b->id));
        usort($listenerData, static fn (ListenerData $a, ListenerData $b): int => strcmp($a->id, $b->id));

        return [$eventData, $listenerData];
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    /**
     * @param  array<string, string>  $handled  Event class to method.
     * @param  array<string, true>  $eventClasses
     */
    private function listener(string $class, array $handled, DiscoveryContext $context, array $eventClasses): ListenerData
    {
        $warnings = [];
        $dispatches = [];

        foreach (array_unique(array_values($handled)) as $method) {
            try {
                $reflection = new ReflectionMethod($class, $method);
            } catch (Throwable) {
                $warnings[] = sprintf('Listener method [%s] does not exist.', $method);

                continue;
            }

            $findings = $this->inspector->inspect($reflection, $context, $eventClasses);

            if (! $findings->readable) {
                $warnings[] = sprintf('Source of [%s] could not be read.', $method);
            }

            $dispatches = [...$dispatches, ...$findings->dispatches];
        }

        $unique = [];

        foreach ($dispatches as $dispatch) {
            $unique[$dispatch->kind->value . '|' . $dispatch->class . '|' . implode(',', $dispatch->via)] ??= $dispatch;
        }

        ksort($unique, SORT_STRING);

        return new ListenerData(
            id: StableIdentifier::listener($class),
            class: $class,
            file: $this->file($class, $context),
            events: $handled,
            queued: $this->classifier->isQueued($class),
            dispatches: array_values($unique),
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * @return array{0: string, 1: string}|null Null for closures and objects.
     */
    private function callable(mixed $listener): ?array
    {
        if ($listener instanceof Closure) {
            return null;
        }

        if (is_array($listener) && isset($listener[0], $listener[1]) && is_string($listener[1])) {
            $class = is_object($listener[0]) ? $listener[0]::class : $listener[0];

            return is_string($class) ? [ltrim($class, '\\'), $listener[1]] : null;
        }

        if (! is_string($listener)) {
            return null;
        }

        $parts = explode('@', $listener, 2);
        $class = ltrim($parts[0], '\\');
        $method = $parts[1] ?? null;

        if ($method === null) {
            $method = method_exists($class, 'handle') ? 'handle' : '__invoke';
        }

        return [$class, $method];
    }

    private function isClassEvent(string $event): bool
    {
        if (str_contains($event, '*')) {
            return false;
        }

        try {
            return class_exists($event) || interface_exists($event);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rawListeners(): array
    {
        if (! method_exists($this->dispatcher, 'getRawListeners')) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'event_dispatcher_not_readable',
                message: sprintf('The event dispatcher [%s] does not expose its listeners.', $this->dispatcher::class),
            );

            return [];
        }

        $listeners = [];

        foreach ($this->dispatcher->getRawListeners() as $event => $registered) {
            $listeners[(string) $event] = is_array($registered) ? array_values($registered) : [$registered];
        }

        return $listeners;
    }

    private function file(string $class, DiscoveryContext $context): ?string
    {
        try {
            $path = class_exists($class) || interface_exists($class) ? (new ReflectionClass($class))->getFileName() : false;
        } catch (Throwable) {
            return null;
        }

        return is_string($path) ? PackagePath::relative($path, $context->basePath) : null;
    }
}
