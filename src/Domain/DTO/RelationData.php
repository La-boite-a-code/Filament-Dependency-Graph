<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;

final readonly class RelationData
{
    /**
     * The specification describes nullability as a boolean. Nullability is
     * only stored when it has been verified against schema metadata, so an
     * unknown state is represented by null instead of a misleading false.
     *
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $sourceModelId,
        public ?string $targetModelId,
        public string $method,
        public RelationType $type,
        public ?string $relatedClass,
        public ?string $foreignKey,
        public ?string $ownerKey,
        public ?string $localKey,
        public ?string $pivotTable,
        public ?string $morphType,
        public ?bool $nullable,
        public bool $polymorphic,
        public bool $inverseDiscovered,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, source_model_id: string, target_model_id: string|null, method: string, type: string, related_class: string|null, foreign_key: string|null, owner_key: string|null, local_key: string|null, pivot_table: string|null, morph_type: string|null, nullable: bool|null, polymorphic: bool, inverse_discovered: bool, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            sourceModelId: $data['source_model_id'],
            targetModelId: $data['target_model_id'],
            method: $data['method'],
            type: RelationType::from($data['type']),
            relatedClass: $data['related_class'],
            foreignKey: $data['foreign_key'],
            ownerKey: $data['owner_key'],
            localKey: $data['local_key'],
            pivotTable: $data['pivot_table'],
            morphType: $data['morph_type'],
            nullable: $data['nullable'],
            polymorphic: $data['polymorphic'],
            inverseDiscovered: $data['inverse_discovered'],
            status: DiscoveryStatus::from($data['status']),
            warnings: $data['warnings'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_model_id' => $this->sourceModelId,
            'target_model_id' => $this->targetModelId,
            'method' => $this->method,
            'type' => $this->type->value,
            'related_class' => $this->relatedClass,
            'foreign_key' => $this->foreignKey,
            'owner_key' => $this->ownerKey,
            'local_key' => $this->localKey,
            'pivot_table' => $this->pivotTable,
            'morph_type' => $this->morphType,
            'nullable' => $this->nullable,
            'polymorphic' => $this->polymorphic,
            'inverse_discovered' => $this->inverseDiscovered,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }

    public function withInverseDiscovered(bool $inverseDiscovered): self
    {
        return new self(
            id: $this->id,
            sourceModelId: $this->sourceModelId,
            targetModelId: $this->targetModelId,
            method: $this->method,
            type: $this->type,
            relatedClass: $this->relatedClass,
            foreignKey: $this->foreignKey,
            ownerKey: $this->ownerKey,
            localKey: $this->localKey,
            pivotTable: $this->pivotTable,
            morphType: $this->morphType,
            nullable: $this->nullable,
            polymorphic: $this->polymorphic,
            inverseDiscovered: $inverseDiscovered,
            status: $this->status,
            warnings: $this->warnings,
        );
    }
}
