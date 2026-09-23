<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use LaBoiteACode\DependencyGraph\Contracts\HttpMapDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\EventData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use ReflectionClass;
use Throwable;

/**
 * Routes first, then the controllers and form requests they point to, the
 * event listener map, and finally every job, mailable, notification and
 * event reached through a dispatch.
 */
final class LaravelHttpMapDiscoverer implements CollectsDiscoveryWarnings, HttpMapDiscoverer
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly RouteDiscoverer $routes,
        private readonly ControllerDiscoverer $controllers,
        private readonly FormRequestDiscoverer $formRequests,
        private readonly EventDiscoverer $events,
        private readonly DispatchableCollector $dispatchables,
    ) {}

    public function discover(DiscoveryContext $context): HttpMapData
    {
        if (! $context->discoverHttp) {
            return new HttpMapData;
        }

        try {
            return $this->map($context);
        } finally {
            // Drained even when a step throws, so no warning leaks into the
            // next discovery run of these long-lived services.
            $this->drain($this->routes);
            $this->drain($this->events);
        }
    }

    private function map(DiscoveryContext $context): HttpMapData
    {
        $eventClasses = $this->events->eventClasses();
        $routes = $this->routes->discover($context);
        $controllers = $this->controllers->discover($routes, $context, $eventClasses);

        $formRequestClasses = [];
        $seeds = [];

        foreach ($controllers as $controller) {
            foreach ($controller->actions as $action) {
                array_push($formRequestClasses, ...$action->formRequests);
                array_push($seeds, ...$action->dispatches);
            }
        }

        [$events, $listeners] = $this->events->discover($context, $eventClasses);

        foreach ($listeners as $listener) {
            array_push($seeds, ...$listener->dispatches);
        }

        $dispatchables = $this->dispatchables->collect($seeds, $context, $eventClasses);

        foreach ($dispatchables as $dispatchable) {
            array_push($seeds, ...$dispatchable->dispatches);
        }

        return new HttpMapData(
            routes: $routes,
            controllers: $controllers,
            formRequests: $this->formRequests->discover($formRequestClasses, $context),
            policies: [],
            events: $this->withDispatchedEvents($events, $seeds, $context),
            listeners: $listeners,
            dispatchables: $dispatchables,
        );
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    /**
     * Events dispatched by the application take part in the map even when
     * nothing listens to them.
     *
     * @param  list<EventData>  $events
     * @param  list<DispatchReference>  $dispatches
     * @return list<EventData>
     */
    private function withDispatchedEvents(array $events, array $dispatches, DiscoveryContext $context): array
    {
        $byId = [];

        foreach ($events as $event) {
            $byId[$event->id] = $event;
        }

        foreach ($dispatches as $dispatch) {
            if ($dispatch->kind !== DispatchKind::Event || isset($byId[$dispatch->targetId()])) {
                continue;
            }

            $byId[$dispatch->targetId()] = new EventData(
                id: $dispatch->targetId(),
                class: $dispatch->class,
                file: $this->file($dispatch->class, $context),
                listenerClasses: [],
                closureListenerCount: 0,
                status: DiscoveryStatus::Complete,
                warnings: [],
            );
        }

        ksort($byId, SORT_STRING);

        return array_values($byId);
    }

    private function file(string $class, DiscoveryContext $context): ?string
    {
        try {
            $path = class_exists($class) ? (new ReflectionClass($class))->getFileName() : false;
        } catch (Throwable) {
            return null;
        }

        return is_string($path) ? PackagePath::relative($path, $context->basePath) : null;
    }

    private function drain(CollectsDiscoveryWarnings $discoverer): void
    {
        $this->warnings = [...$this->warnings, ...$discoverer->pullWarnings()];
    }
}
