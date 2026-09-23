<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Graph;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

/**
 * Turns the HTTP map into nodes and edges. Several controller actions
 * touching the same class collapse into one edge listing the methods.
 */
final class HttpGraphAssembler
{
    public function __construct(
        private readonly NodeFactory $nodes,
        private readonly EdgeFactory $edges,
    ) {}

    /**
     * @return array{0: list<Node>, 1: list<Edge>}
     */
    public function assemble(HttpMapData $http): array
    {
        $nodes = [];
        $edges = [];

        foreach ($http->routes as $route) {
            $nodes[] = $this->nodes->forRoute($route);
            array_push($edges, ...$this->routeEdges($route));
        }

        foreach ($http->controllers as $controller) {
            $nodes[] = $this->nodes->forController($controller);

            $formRequests = [];
            $models = [];
            $dispatches = [];

            foreach ($controller->actions as $method => $action) {
                foreach ($action->formRequests as $class) {
                    $formRequests[$class][] = $method;
                }

                foreach ($action->models as $class => $sources) {
                    $models[$class] ??= ['methods' => [], 'sources' => []];
                    $models[$class]['methods'][] = $method;
                    array_push($models[$class]['sources'], ...$sources);
                }

                foreach ($action->dispatches as $dispatch) {
                    $dispatches[] = [$method, $dispatch];
                }
            }

            foreach ($formRequests as $class => $methods) {
                $edges[] = $this->edges->http(
                    EdgeType::ControllerValidatesWith,
                    $controller->id,
                    StableIdentifier::formRequest($class),
                    implode(', ', $methods),
                    ['methods' => $methods],
                );
            }

            foreach ($models as $class => $usage) {
                $sources = array_values(array_unique($usage['sources']));
                sort($sources, SORT_STRING);

                $edges[] = $this->edges->http(
                    EdgeType::ControllerUsesModel,
                    $controller->id,
                    StableIdentifier::model($class),
                    implode(', ', $usage['methods']),
                    ['methods' => $usage['methods'], 'sources' => $sources, 'model_class' => $class],
                );
            }

            array_push($edges, ...$this->dispatchEdges($controller->id, $dispatches));
        }

        foreach ($http->formRequests as $request) {
            $nodes[] = $this->nodes->forFormRequest($request);
        }

        $policyModels = [];

        foreach ($http->policies as $policy) {
            $policyModels[$policy->id][] = $policy->modelClass;
        }

        foreach ($http->policies as $policy) {
            if (isset($policyModels[$policy->id])) {
                $nodes[] = $this->nodes->forPolicy($policy, $policyModels[$policy->id]);
                unset($policyModels[$policy->id]);
            }

            $edges[] = $this->edges->http(
                EdgeType::ModelGuardedByPolicy,
                StableIdentifier::model($policy->modelClass),
                $policy->id,
                'policy',
                ['source' => $policy->source],
            );
        }

        foreach ($http->listeners as $listener) {
            $nodes[] = $this->nodes->forListener($listener);

            foreach ($listener->events as $event => $method) {
                $edges[] = $this->edges->http(
                    EdgeType::EventHandledByListener,
                    StableIdentifier::event($event),
                    $listener->id,
                    $method,
                    ['method' => $method],
                );
            }

            array_push($edges, ...$this->dispatchEdges(
                $listener->id,
                array_map(static fn (DispatchReference $dispatch): array => [null, $dispatch], $listener->dispatches),
            ));
        }

        foreach ($http->dispatchables as $dispatchable) {
            $nodes[] = $this->nodes->forDispatchable($dispatchable);
            array_push($edges, ...$this->dispatchEdges(
                $dispatchable->id,
                array_map(static fn (DispatchReference $dispatch): array => [null, $dispatch], $dispatchable->dispatches),
            ));
        }

        $dispatchedEventIds = [];

        foreach ($edges as $edge) {
            if ($edge->type === EdgeType::Dispatches) {
                $dispatchedEventIds[$edge->target->value] = true;
            }
        }

        foreach ($http->events as $event) {
            $nodes[] = $this->nodes->forEvent($event, isset($dispatchedEventIds[$event->id]) ? [] : ['No dispatcher found']);
        }

        return [$nodes, $edges];
    }

    /**
     * @return list<Edge>
     */
    private function routeEdges(RouteData $route): array
    {
        if ($route->controllerClass !== null && $route->controllerMethod !== null) {
            return [$this->edges->http(
                EdgeType::RouteHandledByController,
                $route->id,
                StableIdentifier::controller($route->controllerClass),
                $route->controllerMethod,
                ['method' => $route->controllerMethod],
            )];
        }

        if ($route->livewireClass !== null) {
            return [$this->edges->http(
                EdgeType::RouteRendersLivewire,
                $route->id,
                StableIdentifier::livewireComponent($route->livewireClass),
                'renders',
            )];
        }

        return [];
    }

    /**
     * @param  list<array{0: string|null, 1: DispatchReference}>  $dispatches  Source method and reference.
     * @return list<Edge>
     */
    private function dispatchEdges(string $sourceId, array $dispatches): array
    {
        $grouped = [];

        foreach ($dispatches as [$method, $dispatch]) {
            $targetId = $dispatch->targetId();
            $grouped[$targetId] ??= ['kind' => $dispatch->kind, 'methods' => [], 'via' => [], 'locations' => []];

            if ($method !== null) {
                $grouped[$targetId]['methods'][] = $method;
            }

            array_push($grouped[$targetId]['via'], ...$dispatch->via);
            $grouped[$targetId]['locations'][] = $dispatch->location;
        }

        $edges = [];

        foreach ($grouped as $targetId => $group) {
            $edges[] = $this->edges->http(
                EdgeType::Dispatches,
                $sourceId,
                $targetId,
                $group['kind']->value,
                [
                    'kind' => $group['kind']->value,
                    'methods' => array_values(array_unique($group['methods'])),
                    'via' => array_values(array_unique($group['via'])),
                    'locations' => array_values(array_unique($group['locations'])),
                    'confidence' => 'static',
                ],
            );
        }

        return $edges;
    }
}
