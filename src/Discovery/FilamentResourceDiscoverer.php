<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Illuminate\Database\Eloquent\Model;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Contracts\ResourceDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\ResourceData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use Throwable;

final class FilamentResourceDiscoverer implements CollectsDiscoveryWarnings, ResourceDiscoverer
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly FilamentAdapter $adapter,
        private readonly FilamentPageDiscoverer $pages,
        private readonly FilamentRelationManagerDiscoverer $relationManagers,
    ) {}

    public function discover(DiscoveryContext $context): array
    {
        $panelIdsByResource = [];

        foreach ($this->adapter->panels() as $panel) {
            try {
                $panelId = $panel->getId();

                if ($context->panelIds !== [] && ! in_array($panelId, $context->panelIds, true)) {
                    continue;
                }

                foreach ($panel->getResources() as $resourceClass) {
                    $panelIdsByResource[ClassName::normalize($resourceClass)][] = $panelId;
                }
            } catch (Throwable $exception) {
                $this->warnings[] = new DiscoveryWarning(
                    type: 'panel_discovery_failed',
                    message: sprintf('A Filament panel could not be inspected: %s', $exception->getMessage()),
                    exceptionClass: $exception::class,
                );
            }
        }

        ksort($panelIdsByResource, SORT_STRING);

        $resources = [];

        foreach ($panelIdsByResource as $resourceClass => $panelIds) {
            sort($panelIds, SORT_STRING);

            /** @var class-string $resourceClass */
            $resource = $this->discoverResource($resourceClass, array_values(array_unique($panelIds)));

            if (! isset($resources[$resource->id])) {
                $resources[$resource->id] = $resource;
            }
        }

        ksort($resources, SORT_STRING);

        return array_values($resources);
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    /**
     * @param  class-string  $resourceClass
     * @param  list<string>  $panelIds
     */
    private function discoverResource(string $resourceClass, array $panelIds): ResourceData
    {
        $shortName = ClassName::shortName($resourceClass);
        $warnings = [];
        $status = DiscoveryStatus::Complete;

        $modelClass = '';
        $modelId = null;

        try {
            $modelClass = ClassName::normalize($this->adapter->resourceModel($resourceClass));

            if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
                $modelId = StableIdentifier::model($modelClass);
            } else {
                $warnings[] = sprintf('Resource model [%s] is not a loadable Eloquent model.', $modelClass);
                $status = DiscoveryStatus::Partial;
            }
        } catch (Throwable $exception) {
            $warnings[] = sprintf('Resource model could not be resolved: %s', $exception->getMessage());
            $status = DiscoveryStatus::Partial;
        }

        $label = $this->guardedString(
            fn (): string => $this->adapter->resourceLabel($resourceClass),
            $shortName,
            $warnings,
            $status,
        );

        $pluralLabel = $this->guardedString(
            fn (): string => $this->adapter->resourcePluralLabel($resourceClass),
            $shortName,
            $warnings,
            $status,
        );

        try {
            $navigationGroup = $this->adapter->resourceNavigationGroup($resourceClass);
        } catch (Throwable) {
            $navigationGroup = null;
        }

        try {
            $navigationIcon = $this->adapter->resourceNavigationIcon($resourceClass);
        } catch (Throwable) {
            $navigationIcon = null;
        }

        try {
            $pages = $this->pages->discover($resourceClass);
        } catch (Throwable $exception) {
            $pages = [];
            $warnings[] = sprintf('Resource pages could not be discovered: %s', $exception->getMessage());
            $status = DiscoveryStatus::Partial;
        }

        try {
            $relationManagers = $this->relationManagers->discover($resourceClass);
        } catch (Throwable $exception) {
            $relationManagers = [];
            $warnings[] = sprintf('Relation managers could not be discovered: %s', $exception->getMessage());
            $status = DiscoveryStatus::Partial;
        }

        return new ResourceData(
            id: StableIdentifier::resource($resourceClass),
            class: $resourceClass,
            shortName: $shortName,
            modelClass: $modelClass,
            modelId: $modelId,
            label: $label,
            pluralLabel: $pluralLabel,
            navigationGroup: $navigationGroup,
            navigationIcon: $navigationIcon,
            panelIds: $panelIds,
            pages: $pages,
            relationManagers: $relationManagers,
            status: $status,
            warnings: $warnings,
        );
    }

    /**
     * @param  callable(): string  $callback
     * @param  list<string>  $warnings
     */
    private function guardedString(
        callable $callback,
        string $fallback,
        array &$warnings,
        DiscoveryStatus &$status,
    ): string {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $warnings[] = sprintf('Resource label could not be resolved: %s', $exception->getMessage());
            $status = DiscoveryStatus::Partial;

            return $fallback;
        }
    }
}
