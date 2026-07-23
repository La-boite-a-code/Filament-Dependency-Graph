<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\ValueObjects;

use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;

final readonly class GraphQuery
{
    /**
     * @param  list<string>  $panelIds
     * @param  list<NodeType>  $nodeTypes
     * @param  list<RelationType>  $relationTypes
     */
    public function __construct(
        public GraphScope $scope = GraphScope::Filament,
        public array $panelIds = [],
        public array $nodeTypes = [],
        public array $relationTypes = [],
        public ?string $focusNodeId = null,
        public ?int $depth = null,
        public TraversalDirection $direction = TraversalDirection::Both,
        public bool $includeOrphans = true,
    ) {}

    public function hasFocus(): bool
    {
        return $this->focusNodeId !== null && $this->focusNodeId !== '';
    }

    /**
     * Deterministic array representation, used in export metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope->value,
            'panel_ids' => $this->panelIds,
            'node_types' => array_map(
                static fn (NodeType $type): string => $type->value,
                $this->nodeTypes,
            ),
            'relation_types' => array_map(
                static fn (RelationType $type): string => $type->value,
                $this->relationTypes,
            ),
            'focus_node_id' => $this->focusNodeId,
            'depth' => $this->depth,
            'direction' => $this->direction->value,
            'include_orphans' => $this->includeOrphans,
        ];
    }
}
