<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Inspection;

use LaBoiteACode\DependencyGraph\Contracts\NodeInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\InspectionSection;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Support\ClassName;

/**
 * Inspector for every node of the HTTP map: routes, controllers, form
 * requests, policies, events, listeners, jobs, mailables and notifications.
 */
final class HttpNodeInspector implements NodeInspector
{
    public function supports(Node $node): bool
    {
        return $node->type->isHttp();
    }

    public function inspect(Node $node, Graph $graph): InspectionData
    {
        $sections = match ($node->type) {
            NodeType::Route => $this->route($node),
            NodeType::Controller => $this->controller($node, $graph),
            NodeType::FormRequest => $this->formRequest($node, $graph),
            NodeType::Policy => $this->policy($node),
            NodeType::Event => $this->event($node, $graph),
            NodeType::Listener => $this->listener($node, $graph),
            default => $this->dispatchable($node, $graph),
        };

        return new InspectionData(
            subjectId: $node->id->value,
            subjectType: $node->type->value,
            title: $node->label,
            subtitle: $node->subtitle,
            sections: [...$sections, $this->diagnostics($node)],
        );
    }

    /**
     * @return list<InspectionSection>
     */
    private function route(Node $node): array
    {
        $bindings = [];

        foreach ($this->map($node, 'bound_parameters') as $parameter => $class) {
            $bindings[] = sprintf('{%s}: %s', $parameter, ClassName::shortName($class));
        }

        return [
            new InspectionSection('identity', 'Route', [
                'Methods' => $this->stringList($node, 'methods'),
                'URI' => '/' . ltrim((string) $this->string($node, 'uri'), '/'),
                'Name' => $this->string($node, 'name'),
                'Domain' => $this->string($node, 'domain'),
            ]),
            new InspectionSection('action', 'Action', [
                'Type' => $this->string($node, 'action_type'),
                'Controller' => $this->string($node, 'controller_class'),
                'Method' => $this->string($node, 'controller_method'),
                'Livewire component' => $this->string($node, 'livewire_class'),
                'View' => $this->string($node, 'view'),
                'File' => $this->string($node, 'file'),
            ]),
            new InspectionSection('middleware', 'Middleware', [
                'Middleware' => $this->stringList($node, 'middleware'),
            ]),
            new InspectionSection('models', 'Route model binding', [
                'Bound parameters' => $bindings,
            ]),
        ];
    }

    /**
     * @return list<InspectionSection>
     */
    private function controller(Node $node, Graph $graph): array
    {
        $routesByMethod = [];

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type === EdgeType::RouteHandledByController) {
                $routesByMethod[$edge->label][] = $graph->node($edge->source)->label ?? $edge->source->value;
            }
        }

        $actions = [];
        $rawActions = $node->metadata['actions'] ?? [];

        foreach (is_array($rawActions) ? $rawActions : [] as $method => $action) {
            if (! is_array($action)) {
                continue;
            }

            $lines = [];

            foreach ($routesByMethod[$method] ?? [] as $route) {
                $lines[] = 'Route: ' . $route;
            }

            foreach (is_array($action['form_requests'] ?? null) ? $action['form_requests'] : [] as $request) {
                $lines[] = 'Validates with: ' . ClassName::shortName((string) $request);
            }

            foreach (is_array($action['models'] ?? null) ? $action['models'] : [] as $model => $sources) {
                $lines[] = sprintf(
                    'Model: %s (%s)',
                    ClassName::shortName((string) $model),
                    implode(', ', array_map('strval', is_array($sources) ? $sources : [])),
                );
            }

            foreach (is_array($action['dispatches'] ?? null) ? $action['dispatches'] : [] as $dispatch) {
                if (is_array($dispatch)) {
                    $lines[] = 'Dispatches: ' . $this->describeDispatch($dispatch);
                }
            }

            $actions[(string) $method] = $lines;
        }

        return [
            new InspectionSection('identity', 'Identity', [
                'Class' => $this->string($node, 'class'),
                'File' => $this->string($node, 'file'),
            ]),
            new InspectionSection('actions', 'Actions', $actions),
        ];
    }

    /**
     * @return list<InspectionSection>
     */
    private function formRequest(Node $node, Graph $graph): array
    {
        $rules = [];
        $rawRules = $node->metadata['rules'] ?? null;

        if (is_array($rawRules)) {
            foreach ($rawRules as $field => $fieldRules) {
                $rules[] = sprintf('%s: %s', $field, implode('|', array_map('strval', is_array($fieldRules) ? $fieldRules : [])));
            }
        }

        $usedBy = [];

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type === EdgeType::ControllerValidatesWith) {
                $usedBy[] = sprintf('%s (%s)', $graph->node($edge->source)->label ?? $edge->source->value, $edge->label);
            }
        }

        return [
            new InspectionSection('identity', 'Identity', [
                'Class' => $this->string($node, 'class'),
                'File' => $this->string($node, 'file'),
                'Used by' => $usedBy,
            ]),
            new InspectionSection('validation', 'Validation', [
                'authorize()' => $this->bool($node, 'has_authorize'),
                'rules()' => $this->bool($node, 'has_rules'),
                'Rules' => is_array($rawRules)
                    ? $rules
                    : 'Not read. Enable "http.form_request_rules" to read the rules.',
            ]),
        ];
    }

    /**
     * @return list<InspectionSection>
     */
    private function policy(Node $node): array
    {
        return [
            new InspectionSection('identity', 'Identity', [
                'Class' => $this->string($node, 'class'),
                'File' => $this->string($node, 'file'),
                'Model' => $this->string($node, 'model_class'),
                'Resolved by' => $this->string($node, 'source'),
            ]),
            new InspectionSection('abilities', 'Abilities', [
                'Abilities' => $this->stringList($node, 'abilities'),
            ]),
        ];
    }

    /**
     * @return list<InspectionSection>
     */
    private function event(Node $node, Graph $graph): array
    {
        $listeners = [];

        foreach ($graph->outgoingEdges($node->id) as $edge) {
            if ($edge->type === EdgeType::EventHandledByListener) {
                $listeners[] = sprintf('%s@%s', $graph->node($edge->target)->label ?? $edge->target->value, $edge->label);
            }
        }

        $closures = $node->metadata['closure_listener_count'] ?? 0;

        return [
            new InspectionSection('identity', 'Identity', [
                'Class' => $this->string($node, 'class'),
                'File' => $this->string($node, 'file'),
            ]),
            new InspectionSection('listeners', 'Listeners', [
                'Listeners' => $listeners,
                'Closure listeners' => is_int($closures) ? $closures : 0,
            ]),
            new InspectionSection('dispatched_by', 'Dispatched by', [
                'Sources' => $this->dispatchedBy($node, $graph),
            ]),
        ];
    }

    /**
     * @return list<InspectionSection>
     */
    private function listener(Node $node, Graph $graph): array
    {
        $events = [];

        foreach ($this->map($node, 'events') as $event => $method) {
            $events[] = sprintf('%s → %s()', ClassName::shortName($event), $method);
        }

        return [
            new InspectionSection('identity', 'Identity', [
                'Class' => $this->string($node, 'class'),
                'File' => $this->string($node, 'file'),
                'Queued' => $this->bool($node, 'queued'),
            ]),
            new InspectionSection('listeners', 'Handles', [
                'Events' => $events,
            ]),
            new InspectionSection('dispatches', 'Dispatches', [
                'Dispatches' => $this->dispatchList($node),
            ]),
        ];
    }

    /**
     * @return list<InspectionSection>
     */
    private function dispatchable(Node $node, Graph $graph): array
    {
        return [
            new InspectionSection('identity', 'Identity', [
                'Class' => $this->string($node, 'class'),
                'Kind' => $this->string($node, 'kind'),
                'File' => $this->string($node, 'file'),
                'Queued' => $this->bool($node, 'queued'),
            ]),
            new InspectionSection('dispatched_by', 'Dispatched by', [
                'Sources' => $this->dispatchedBy($node, $graph),
            ]),
            new InspectionSection('dispatches', 'Dispatches', [
                'Dispatches' => $this->dispatchList($node),
            ]),
        ];
    }

    /**
     * @return list<string>
     */
    private function dispatchedBy(Node $node, Graph $graph): array
    {
        $sources = [];

        foreach ($graph->incomingEdges($node->id) as $edge) {
            if ($edge->type !== EdgeType::Dispatches) {
                continue;
            }

            $sources[] = $this->describeIncomingDispatch($edge, $graph);
        }

        sort($sources, SORT_STRING);

        return $sources;
    }

    private function describeIncomingDispatch(Edge $edge, Graph $graph): string
    {
        $source = $graph->node($edge->source)->label ?? $edge->source->value;
        $methods = $edge->metadata['methods'] ?? [];
        $via = $edge->metadata['via'] ?? [];
        $locations = $edge->metadata['locations'] ?? [];

        if (is_array($methods) && $methods !== []) {
            $source .= '@' . implode(', ', array_map('strval', $methods));
        }

        if (is_array($via) && $via !== []) {
            $source .= ' via ' . implode(', ', array_map(static fn (mixed $class): string => ClassName::shortName((string) $class), $via));
        }

        if (is_array($locations) && $locations !== []) {
            $source .= ' (' . implode(', ', array_map('strval', $locations)) . ')';
        }

        return $source;
    }

    /**
     * @return list<string>
     */
    private function dispatchList(Node $node): array
    {
        $dispatches = [];
        $raw = $node->metadata['dispatches'] ?? [];

        foreach (is_array($raw) ? $raw : [] as $dispatch) {
            if (is_array($dispatch)) {
                $dispatches[] = $this->describeDispatch($dispatch);
            }
        }

        return $dispatches;
    }

    /**
     * @param  array<mixed>  $dispatch
     */
    private function describeDispatch(array $dispatch): string
    {
        $description = sprintf(
            '%s %s',
            (string) ($dispatch['kind'] ?? ''),
            ClassName::shortName((string) ($dispatch['class'] ?? '')),
        );

        $via = $dispatch['via'] ?? [];

        if (is_array($via) && $via !== []) {
            $description .= ' via ' . implode(', ', array_map(static fn (mixed $class): string => ClassName::shortName((string) $class), $via));
        }

        if (is_string($dispatch['location'] ?? null)) {
            $description .= ' (' . $dispatch['location'] . ')';
        }

        return $description;
    }

    private function diagnostics(Node $node): InspectionSection
    {
        return new InspectionSection('diagnostics', 'Diagnostics', [
            'Status' => $node->status->value,
            'Badges' => $node->badges,
            'Warnings' => $this->stringList($node, 'warnings'),
        ]);
    }

    private function string(Node $node, string $key): ?string
    {
        $value = $node->metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function bool(Node $node, string $key): string
    {
        return ($node->metadata[$key] ?? false) === true ? 'yes' : 'no';
    }

    /**
     * @return list<string>
     */
    private function stringList(Node $node, string $key): array
    {
        $values = $node->metadata[$key] ?? [];

        return array_values(array_filter(is_array($values) ? $values : [], 'is_string'));
    }

    /**
     * @return array<string, string>
     */
    private function map(Node $node, string $key): array
    {
        $values = $node->metadata[$key] ?? [];
        $map = [];

        foreach (is_array($values) ? $values : [] as $name => $value) {
            if (is_string($value)) {
                $map[(string) $name] = $value;
            }
        }

        return $map;
    }
}
