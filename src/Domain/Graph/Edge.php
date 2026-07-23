<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;

final readonly class Edge
{
    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $metadata
     */
    public function __construct(
        public EdgeId $id,
        public NodeId $source,
        public NodeId $target,
        public EdgeType $type,
        public string $label,
        public array $metadata,
        public DiscoveryStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, source: string, target: string, type: string, label: string, metadata: array<string, scalar|array<array-key, mixed>|null>, status: string} $data */
        return new self(
            id: EdgeId::fromString($data['id']),
            source: NodeId::fromString($data['source']),
            target: NodeId::fromString($data['target']),
            type: EdgeType::from($data['type']),
            label: $data['label'],
            metadata: $data['metadata'],
            status: DiscoveryStatus::from($data['status']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'source' => $this->source->value,
            'target' => $this->target->value,
            'type' => $this->type->value,
            'label' => $this->label,
            'metadata' => $this->metadata,
            'status' => $this->status->value,
        ];
    }

    public function opposite(NodeId $nodeId): NodeId
    {
        return $this->source->equals($nodeId) ? $this->target : $this->source;
    }

    public function touches(NodeId $nodeId): bool
    {
        return $this->source->equals($nodeId) || $this->target->equals($nodeId);
    }
}
