<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationManagerData;

final class FilamentRelationManagerDiscoverer
{
    public function __construct(
        private readonly FilamentAdapter $adapter,
    ) {}

    /**
     * @param  class-string  $resource
     * @return list<RelationManagerData>
     */
    public function discover(string $resource): array
    {
        $managers = [];

        foreach ($this->adapter->resourceRelationManagers($resource) as $managerClass) {
            $managers[] = new RelationManagerData(
                class: $managerClass,
                relationship: $this->adapter->relationManagerRelationship($managerClass),
                relatedResource: $this->adapter->relationManagerRelatedResource($managerClass),
                title: $this->adapter->relationManagerTitle($managerClass),
            );
        }

        usort(
            $managers,
            static fn (RelationManagerData $a, RelationManagerData $b): int => strcmp($a->class, $b->class),
        );

        return $managers;
    }
}
