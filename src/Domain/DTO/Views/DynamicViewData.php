<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

/**
 * A view name only known at runtime.
 */
final readonly class DynamicViewData
{
    public function __construct(
        public string $id,
        public string $sourceViewId,
        public string $directive,
        public string $expression,
        public int $line,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, source_view_id: string, directive: string, expression: string, line: int} $data */
        return new self(
            id: $data['id'],
            sourceViewId: $data['source_view_id'],
            directive: $data['directive'],
            expression: $data['expression'],
            line: $data['line'],
        );
    }

    /**
     * @return array{id: string, source_view_id: string, directive: string, expression: string, line: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_view_id' => $this->sourceViewId,
            'directive' => $this->directive,
            'expression' => $this->expression,
            'line' => $this->line,
        ];
    }
}
