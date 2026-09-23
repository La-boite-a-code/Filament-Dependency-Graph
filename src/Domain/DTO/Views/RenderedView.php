<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

/**
 * A view an owner renders, and how it does.
 */
final readonly class RenderedView
{
    public function __construct(
        public string $targetId,
        public string $name,
        public string $how,
        public ?string $method,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{target_id: string, name: string, how: string, method: string|null} $data */
        return new self(
            targetId: $data['target_id'],
            name: $data['name'],
            how: $data['how'],
            method: $data['method'],
        );
    }

    /**
     * @return array{target_id: string, name: string, how: string, method: string|null}
     */
    public function toArray(): array
    {
        return [
            'target_id' => $this->targetId,
            'name' => $this->name,
            'how' => $this->how,
            'method' => $this->method,
        ];
    }
}
