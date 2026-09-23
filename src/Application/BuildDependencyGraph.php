<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use Illuminate\Contracts\Config\Repository;
use LaBoiteACode\DependencyGraph\Contracts\GraphBuilder;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Edge;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Graph\OrphanDetector;

/**
 * Builds the full graph from a snapshot, then applies a graph query:
 * relation type filters, panel filters, scope restriction, node type
 * filters, orphan visibility and focus traversal.
 */
final class BuildDependencyGraph
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly FocusDependencyGraph $focus,
        private readonly OrphanDetector $orphans,
        private readonly Repository $config,
    ) {}

    public function execute(ApplicationSnapshot $snapshot, ?GraphQuery $query = null): Graph
    {
        $graph = $this->builder->build(
            in_array($query?->scope, [GraphScope::Filament, GraphScope::Laravel], true)
                ? $this->withoutMaps($snapshot)
                : $snapshot,
        );

        if ($query === null) {
            return $graph;
        }

        $graph = $this->filterRelationTypes($graph, $query);
        $graph = $this->filterPanels($graph, $query);

        $graph = match ($query->scope) {
            GraphScope::Filament => $this->restrictToFilamentScope($graph),
            GraphScope::Laravel => $this->withoutHttpNodes($graph),
            GraphScope::Http => $this->restrictToHttpScope($graph, $query->middleware),
            GraphScope::Views => $this->restrictToViewsScope($graph),
        };

        $graph = $this->filterNodeTypes($graph, $query);

        if (! $query->includeOrphans) {
            $graph = $this->withoutOrphans($graph, $query->scope);
        }

        if ($query->hasFocus() && $query->focusNodeId !== null && $graph->hasNode($query->focusNodeId)) {
            $graph = $this->focus->execute(
                graph: $graph,
                nodeId: $query->focusNodeId,
                depth: $query->depth,
                direction: $query->direction,
            )->graph;
        }

        return $graph;
    }

    /**
     * The Filament and Laravel scopes drop every HTTP and view node: they
     * are not built at all. Model badges only depend on relations,
     * resources and Livewire components, so they are unchanged.
     */
    private function withoutMaps(ApplicationSnapshot $snapshot): ApplicationSnapshot
    {
        if ($snapshot->http->isEmpty() && $snapshot->views->isEmpty()) {
            return $snapshot;
        }

        return new ApplicationSnapshot(
            fingerprint: $snapshot->fingerprint,
            generatedAt: $snapshot->generatedAt,
            models: $snapshot->models,
            relations: $snapshot->relations,
            resources: $snapshot->resources,
            panels: $snapshot->panels,
            warnings: $snapshot->warnings,
            livewireComponents: $snapshot->livewireComponents,
        );
    }

    private function filterRelationTypes(Graph $graph, GraphQuery $query): Graph
    {
        if ($query->relationTypes === []) {
            return $graph;
        }

        $allowed = array_map(
            static fn (RelationType $type): string => $type->value,
            $query->relationTypes,
        );

        $edgeIds = [];

        foreach ($graph->edges as $edge) {
            if ($edge->type !== EdgeType::ModelRelation) {
                $edgeIds[] = $edge->id->value;

                continue;
            }

            if (in_array($edge->metadata['relation_type'] ?? null, $allowed, true)) {
                $edgeIds[] = $edge->id->value;
            }
        }

        return $graph->subgraph($this->allNodeIds($graph), $edgeIds);
    }

    private function filterPanels(Graph $graph, GraphQuery $query): Graph
    {
        if ($query->panelIds === []) {
            return $graph;
        }

        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if ($this->isVisibleForPanels($node, $query->panelIds)) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    /**
     * @param  list<string>  $panelIds
     */
    private function isVisibleForPanels(Node $node, array $panelIds): bool
    {
        if ($node->type === NodeType::Panel) {
            return in_array($node->metadata['panel_id'] ?? null, $panelIds, true);
        }

        if ($node->type === NodeType::Resource) {
            $resourcePanels = $node->metadata['panel_ids'] ?? [];

            return is_array($resourcePanels)
                && array_intersect($resourcePanels, $panelIds) !== [];
        }

        return true;
    }

    /**
     * The Filament scope starts from resource models and keeps related
     * models up to the configured depth. Panels and resources always stay.
     */
    private function restrictToFilamentScope(Graph $graph): Graph
    {
        $depth = (int) $this->config->get('filament-dependency-graph.graph.default_depth', 2);

        $keep = [];
        $roots = [];

        foreach ($graph->nodes as $node) {
            if ($node->type === NodeType::Panel || $node->type === NodeType::Resource) {
                $keep[$node->id->value] = true;
            }

            if ($node->type === NodeType::Resource) {
                $modelId = $node->metadata['model_id'] ?? null;

                if (is_string($modelId) && $graph->hasNode($modelId)) {
                    $roots[$modelId] = true;
                }
            }
        }

        /** @var list<array{0: string, 1: int}> $queue */
        $queue = [];
        $visited = [];

        foreach (array_keys($roots) as $rootId) {
            $keep[$rootId] = true;
            $visited[$rootId] = true;
            $queue[] = [$rootId, 0];
        }

        $cursor = 0;

        while ($cursor < count($queue)) {
            [$currentId, $currentDepth] = $queue[$cursor];
            $cursor++;

            if ($currentDepth >= $depth) {
                continue;
            }

            $edges = [...$graph->outgoingEdges($currentId), ...$graph->incomingEdges($currentId)];

            foreach ($edges as $edge) {
                if ($edge->type !== EdgeType::ModelRelation) {
                    continue;
                }

                $neighbour = $this->oppositeKey($edge, $currentId);
                $keep[$neighbour] = true;

                if (! isset($visited[$neighbour])) {
                    $visited[$neighbour] = true;
                    $queue[] = [$neighbour, $currentDepth + 1];
                }
            }
        }

        return $graph->subgraph(array_keys($keep));
    }

    /**
     * The Laravel scope keeps its historical content: models, relations,
     * resources and Livewire components, without the HTTP and view maps.
     */
    private function withoutHttpNodes(Graph $graph): Graph
    {
        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if (! $node->type->isHttp() && ! $node->type->isView()) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    /**
     * The HTTP scope starts from the routes and follows what they lead to:
     * controllers, form requests, models and their policies, dispatches,
     * events and listeners. Events and listeners are shown even when no
     * dispatcher was found, unless routes are filtered by middleware.
     */
    private function restrictToHttpScope(Graph $graph, ?string $middleware): Graph
    {
        $followed = [
            EdgeType::RouteHandledByController,
            EdgeType::RouteRendersLivewire,
            EdgeType::ControllerValidatesWith,
            EdgeType::ControllerUsesModel,
            EdgeType::LivewireUsesModel,
            EdgeType::ModelGuardedByPolicy,
            EdgeType::Dispatches,
            EdgeType::EventHandledByListener,
            // One hop to the rendered view: view edges are not followed, so
            // views stay leaves of the HTTP scope.
            EdgeType::RendersView,
        ];

        $middleware = $middleware === null || trim($middleware) === '' ? null : trim($middleware);
        $queue = [];

        foreach ($graph->nodes as $node) {
            $isSeed = match ($node->type) {
                NodeType::Route => $middleware === null || $this->matchesMiddleware($node, $middleware),
                NodeType::Event, NodeType::Listener => $middleware === null,
                default => false,
            };

            if ($isSeed) {
                $queue[] = $node->id->value;
            }
        }

        $keep = array_fill_keys($queue, true);

        // A controller node stands for all its actions: from a controller,
        // only the edges of actions reached by a kept route are followed.
        // Routes are seeded first, so every route is expanded before any
        // controller is.
        $routedMethods = [];

        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            $currentId = $queue[$cursor];

            foreach ($graph->outgoingEdges($currentId) as $edge) {
                if (! in_array($edge->type, $followed, true)) {
                    continue;
                }

                if ($edge->type === EdgeType::RouteHandledByController) {
                    $routedMethods[$edge->target->value][] = $edge->label;
                }

                if (isset($routedMethods[$currentId]) && ! $this->isRoutedEdge($edge, $routedMethods[$currentId])) {
                    continue;
                }

                if (isset($keep[$edge->target->value])) {
                    continue;
                }

                $keep[$edge->target->value] = true;
                $queue[] = $edge->target->value;
            }
        }

        // Views stay leaves: the references between two kept views are dropped.
        $edgeIds = [];

        foreach ($graph->edges as $edge) {
            if (! $edge->type->isViewReference()) {
                $edgeIds[] = $edge->id->value;
            }
        }

        return $graph->subgraph(array_keys($keep), $edgeIds);
    }

    /**
     * The Views scope starts from every application view, Blade component
     * and Filament class, and every owner rendering a view, and follows the
     * view edges down to components,
     * Livewire components, package views and dynamic references.
     */
    private function restrictToViewsScope(Graph $graph): Graph
    {
        $followed = [EdgeType::RendersView, ...EdgeType::viewReferences()];
        $queue = [];

        foreach ($graph->nodes as $node) {
            if (in_array($node->type, [NodeType::View, NodeType::BladeComponent, NodeType::FilamentComponent], true)) {
                $queue[] = $node->id->value;

                continue;
            }

            foreach ($graph->outgoingEdges($node->id) as $edge) {
                if ($edge->type === EdgeType::RendersView) {
                    $queue[] = $node->id->value;

                    break;
                }
            }
        }

        $keep = array_fill_keys($queue, true);

        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            foreach ($graph->outgoingEdges($queue[$cursor]) as $edge) {
                if (in_array($edge->type, $followed, true) && ! isset($keep[$edge->target->value])) {
                    $keep[$edge->target->value] = true;
                    $queue[] = $edge->target->value;
                }
            }
        }

        return $graph->subgraph(array_keys($keep));
    }

    /**
     * @param  list<string>  $routedMethods
     */
    private function isRoutedEdge(Edge $edge, array $routedMethods): bool
    {
        $methods = $edge->metadata['methods'] ?? null;

        return ! is_array($methods) || array_intersect($methods, $routedMethods) !== [];
    }

    /**
     * "auth" keeps routes using the auth middleware (with or without
     * parameters), "!auth" keeps the routes that do not.
     */
    private function matchesMiddleware(Node $route, string $filter): bool
    {
        $negated = str_starts_with($filter, '!');
        $name = ltrim($filter, '!');
        $middleware = $route->metadata['resolved_middleware'] ?? $route->metadata['middleware'] ?? [];
        $found = false;

        foreach (is_array($middleware) ? $middleware : [] as $entry) {
            if (is_string($entry) && ($entry === $name || str_starts_with($entry, $name . ':'))) {
                $found = true;

                break;
            }
        }

        return $negated ? ! $found : $found;
    }

    private function filterNodeTypes(Graph $graph, GraphQuery $query): Graph
    {
        if ($query->nodeTypes === []) {
            return $graph;
        }

        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if (in_array($node->type, $query->nodeTypes, true)) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    /**
     * In the HTTP scope a model used by a controller is connected: hiding
     * orphans must not hide what the routes lead to.
     */
    private function withoutOrphans(Graph $graph, GraphScope $scope): Graph
    {
        $usageEdges = [EdgeType::ResourceUsesModel, EdgeType::LivewireUsesModel];

        if ($scope === GraphScope::Http) {
            $usageEdges[] = EdgeType::ControllerUsesModel;
        }

        $orphans = array_flip($this->orphans->detect($graph, $usageEdges));

        $nodeIds = [];

        foreach ($graph->nodes as $node) {
            if (! isset($orphans[$node->id->value])) {
                $nodeIds[] = $node->id->value;
            }
        }

        return $graph->subgraph($nodeIds);
    }

    /**
     * @return list<string>
     */
    private function allNodeIds(Graph $graph): array
    {
        return array_map(
            static fn (Node $node): string => $node->id->value,
            $graph->nodes,
        );
    }

    private function oppositeKey(Edge $edge, string $nodeId): string
    {
        return $edge->source->value === $nodeId ? $edge->target->value : $edge->source->value;
    }
}
