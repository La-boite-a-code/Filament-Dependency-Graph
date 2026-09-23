<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

/**
 * One reference written in a template: an @include, an <x-…> tag…
 */
final readonly class ViewReference
{
    public const TYPE_EXTENDS = 'extends';

    public const TYPE_INCLUDE = 'include';

    public const TYPE_COMPONENT = 'component';

    public const TYPE_LIVEWIRE = 'livewire';

    public const TYPE_DYNAMIC = 'dynamic';

    public function __construct(
        public string $type,
        public string $written,
        public string $directive,
        public ?string $targetId,
        public int $line,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{type: string, written: string, directive: string, target_id: string|null, line: int} $data */
        return new self(
            type: $data['type'],
            written: $data['written'],
            directive: $data['directive'],
            targetId: $data['target_id'],
            line: $data['line'],
        );
    }

    /**
     * @return array{type: string, written: string, directive: string, target_id: string|null, line: int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'written' => $this->written,
            'directive' => $this->directive,
            'target_id' => $this->targetId,
            'line' => $this->line,
        ];
    }
}
