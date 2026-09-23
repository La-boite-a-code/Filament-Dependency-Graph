<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

/**
 * Everything the HTTP scope shows: routes and what they lead to.
 */
final readonly class HttpMapData
{
    /**
     * @param  list<RouteData>  $routes
     * @param  list<ControllerData>  $controllers
     * @param  list<FormRequestData>  $formRequests
     * @param  list<PolicyData>  $policies
     * @param  list<EventData>  $events
     * @param  list<ListenerData>  $listeners
     * @param  list<DispatchableData>  $dispatchables
     */
    public function __construct(
        public array $routes = [],
        public array $controllers = [],
        public array $formRequests = [],
        public array $policies = [],
        public array $events = [],
        public array $listeners = [],
        public array $dispatchables = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->routes === []
            && $this->controllers === []
            && $this->formRequests === []
            && $this->policies === []
            && $this->events === []
            && $this->listeners === []
            && $this->dispatchables === [];
    }

    /**
     * @param  list<PolicyData>  $policies
     */
    public function withPolicies(array $policies): self
    {
        return new self(
            routes: $this->routes,
            controllers: $this->controllers,
            formRequests: $this->formRequests,
            policies: $policies,
            events: $this->events,
            listeners: $this->listeners,
            dispatchables: $this->dispatchables,
        );
    }

    /**
     * Model classes referenced by route bindings and controller actions.
     *
     * @return list<string>
     */
    public function referencedModelClasses(): array
    {
        $classes = [];

        foreach ($this->routes as $route) {
            foreach ($route->boundParameters as $class) {
                $classes[$class] = true;
            }
        }

        foreach ($this->controllers as $controller) {
            foreach ($controller->actions as $action) {
                foreach (array_keys($action->models) as $class) {
                    $classes[$class] = true;
                }
            }
        }

        $classes = array_keys($classes);
        sort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{routes?: list<array<string, mixed>>, controllers?: list<array<string, mixed>>, form_requests?: list<array<string, mixed>>, policies?: list<array<string, mixed>>, events?: list<array<string, mixed>>, listeners?: list<array<string, mixed>>, dispatchables?: list<array<string, mixed>>} $data */
        return new self(
            routes: array_map(static fn (array $item): RouteData => RouteData::fromArray($item), $data['routes'] ?? []),
            controllers: array_map(static fn (array $item): ControllerData => ControllerData::fromArray($item), $data['controllers'] ?? []),
            formRequests: array_map(static fn (array $item): FormRequestData => FormRequestData::fromArray($item), $data['form_requests'] ?? []),
            policies: array_map(static fn (array $item): PolicyData => PolicyData::fromArray($item), $data['policies'] ?? []),
            events: array_map(static fn (array $item): EventData => EventData::fromArray($item), $data['events'] ?? []),
            listeners: array_map(static fn (array $item): ListenerData => ListenerData::fromArray($item), $data['listeners'] ?? []),
            dispatchables: array_map(static fn (array $item): DispatchableData => DispatchableData::fromArray($item), $data['dispatchables'] ?? []),
        );
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [
            'routes' => array_map(static fn (RouteData $item): array => $item->toArray(), $this->routes),
            'controllers' => array_map(static fn (ControllerData $item): array => $item->toArray(), $this->controllers),
            'form_requests' => array_map(static fn (FormRequestData $item): array => $item->toArray(), $this->formRequests),
            'policies' => array_map(static fn (PolicyData $item): array => $item->toArray(), $this->policies),
            'events' => array_map(static fn (EventData $item): array => $item->toArray(), $this->events),
            'listeners' => array_map(static fn (ListenerData $item): array => $item->toArray(), $this->listeners),
            'dispatchables' => array_map(static fn (DispatchableData $item): array => $item->toArray(), $this->dispatchables),
        ];
    }
}
