<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\ValueObjects;

use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;

/**
 * Immutable description of one discovery run.
 *
 * The context carries every input that can influence discovery output,
 * which also makes it the natural input for cache key generation.
 */
final readonly class DiscoveryContext
{
    /**
     * @param  list<string>  $modelPaths
     * @param  list<string>  $modelNamespaces
     * @param  list<string>  $excludedClasses
     * @param  list<string>  $excludedNamespaces
     * @param  list<string>  $excludedTables
     * @param  list<string>  $excludedRelations  Entries formatted as "Fully\Qualified\Model::method".
     * @param  list<string>  $vendorModelNamespaces
     * @param  list<string>  $livewirePaths
     * @param  list<string>  $livewireNamespaces
     * @param  list<string>  $panelIds  Empty list means every registered panel.
     * @param  list<string>  $httpControllerNamespaces
     * @param  list<string>  $httpApplicationNamespaces
     * @param  list<string>  $excludedRouteNames  Str::is() patterns.
     * @param  list<string>  $excludedRouteUris  Str::is() patterns.
     */
    public function __construct(
        public GraphScope $scope = GraphScope::Filament,
        public array $modelPaths = [],
        public array $modelNamespaces = [],
        public array $excludedClasses = [],
        public array $excludedNamespaces = [],
        public array $excludedTables = [],
        public array $excludedRelations = [],
        public bool $vendorModelsEnabled = false,
        public array $vendorModelNamespaces = [],
        public bool $discoverRelations = true,
        public bool $inspectDatabaseSchema = true,
        public bool $useDocblocks = true,
        public bool $useHeuristicInvocation = false,
        public bool $discoverLivewireComponents = true,
        public array $livewirePaths = [],
        public array $livewireNamespaces = [],
        public array $panelIds = [],
        public string $basePath = '',
        public string $vendorPath = '',
        public bool $discoverHttp = true,
        public array $httpControllerNamespaces = [],
        public array $httpApplicationNamespaces = [],
        public bool $includeVendorRoutes = false,
        public array $excludedRouteNames = [],
        public array $excludedRouteUris = [],
        public bool $followInjectedClasses = true,
        public bool $invokeFormRequestRules = false,
    ) {}

    public function withScope(GraphScope $scope): self
    {
        return $this->clone(scope: $scope);
    }

    /**
     * @param  list<string>  $panelIds
     */
    public function withPanelIds(array $panelIds): self
    {
        return $this->clone(panelIds: $panelIds);
    }

    public function withoutSchemaInspection(): self
    {
        return $this->clone(inspectDatabaseSchema: false);
    }

    /**
     * Deterministic array representation used for cache key fingerprints.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope->value,
            'model_paths' => $this->modelPaths,
            'model_namespaces' => $this->modelNamespaces,
            'excluded_classes' => $this->excludedClasses,
            'excluded_namespaces' => $this->excludedNamespaces,
            'excluded_tables' => $this->excludedTables,
            'excluded_relations' => $this->excludedRelations,
            'vendor_models_enabled' => $this->vendorModelsEnabled,
            'vendor_model_namespaces' => $this->vendorModelNamespaces,
            'discover_relations' => $this->discoverRelations,
            'inspect_database_schema' => $this->inspectDatabaseSchema,
            'use_docblocks' => $this->useDocblocks,
            'use_heuristic_invocation' => $this->useHeuristicInvocation,
            'discover_livewire_components' => $this->discoverLivewireComponents,
            'livewire_paths' => $this->livewirePaths,
            'livewire_namespaces' => $this->livewireNamespaces,
            'panel_ids' => $this->panelIds,
            'base_path' => $this->basePath,
            'vendor_path' => $this->vendorPath,
            'discover_http' => $this->discoverHttp,
            'http_controller_namespaces' => $this->httpControllerNamespaces,
            'http_application_namespaces' => $this->httpApplicationNamespaces,
            'include_vendor_routes' => $this->includeVendorRoutes,
            'excluded_route_names' => $this->excludedRouteNames,
            'excluded_route_uris' => $this->excludedRouteUris,
            'follow_injected_classes' => $this->followInjectedClasses,
            'invoke_form_request_rules' => $this->invokeFormRequestRules,
        ];
    }

    /**
     * @param  list<string>|null  $panelIds
     */
    private function clone(
        ?GraphScope $scope = null,
        ?array $panelIds = null,
        ?bool $inspectDatabaseSchema = null,
    ): self {
        return new self(
            scope: $scope ?? $this->scope,
            modelPaths: $this->modelPaths,
            modelNamespaces: $this->modelNamespaces,
            excludedClasses: $this->excludedClasses,
            excludedNamespaces: $this->excludedNamespaces,
            excludedTables: $this->excludedTables,
            excludedRelations: $this->excludedRelations,
            vendorModelsEnabled: $this->vendorModelsEnabled,
            vendorModelNamespaces: $this->vendorModelNamespaces,
            discoverRelations: $this->discoverRelations,
            inspectDatabaseSchema: $inspectDatabaseSchema ?? $this->inspectDatabaseSchema,
            useDocblocks: $this->useDocblocks,
            useHeuristicInvocation: $this->useHeuristicInvocation,
            discoverLivewireComponents: $this->discoverLivewireComponents,
            livewirePaths: $this->livewirePaths,
            livewireNamespaces: $this->livewireNamespaces,
            panelIds: $panelIds ?? $this->panelIds,
            basePath: $this->basePath,
            vendorPath: $this->vendorPath,
            discoverHttp: $this->discoverHttp,
            httpControllerNamespaces: $this->httpControllerNamespaces,
            httpApplicationNamespaces: $this->httpApplicationNamespaces,
            includeVendorRoutes: $this->includeVendorRoutes,
            excludedRouteNames: $this->excludedRouteNames,
            excludedRouteUris: $this->excludedRouteUris,
            followInjectedClasses: $this->followInjectedClasses,
            invokeFormRequestRules: $this->invokeFormRequestRules,
        );
    }
}
