<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Graph;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;

final readonly class Node
{
    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $metadata
     * @param  list<string>  $badges
     */
    public function __construct(
        public NodeId $id,
        public NodeType $type,
        public string $label,
        public ?string $subtitle,
        public array $metadata,
        public array $badges,
        public DiscoveryStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, type: string, label: string, subtitle: string|null, metadata: array<string, scalar|array<array-key, mixed>|null>, badges: list<string>, status: string} $data */
        return new self(
            id: NodeId::fromString($data['id']),
            type: NodeType::from($data['type']),
            label: $data['label'],
            subtitle: $data['subtitle'],
            metadata: $data['metadata'],
            badges: $data['badges'],
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
            'type' => $this->type->value,
            'label' => $this->label,
            'subtitle' => $this->subtitle,
            'metadata' => $this->metadata,
            'badges' => $this->badges,
            'status' => $this->status->value,
        ];
    }

    /**
     * @param  list<string>  $badges
     */
    public function withBadges(array $badges): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            label: $this->label,
            subtitle: $this->subtitle,
            metadata: $this->metadata,
            badges: $badges,
            status: $this->status,
        );
    }
}
