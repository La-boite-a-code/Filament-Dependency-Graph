<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;

final readonly class SearchResultData
{
    public function __construct(
        public string $nodeId,
        public NodeType $type,
        public string $label,
        public ?string $subtitle,
        public int $score,
        public string $matchedField,
    ) {}

    /**
     * @return array{node_id: string, type: string, label: string, subtitle: string|null, score: int, matched_field: string}
     */
    public function toArray(): array
    {
        return [
            'node_id' => $this->nodeId,
            'type' => $this->type->value,
            'label' => $this->label,
            'subtitle' => $this->subtitle,
            'score' => $this->score,
            'matched_field' => $this->matchedField,
        ];
    }
}
