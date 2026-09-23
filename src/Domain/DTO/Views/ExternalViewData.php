<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

/**
 * A package view or component used by the application, or a view that
 * does not exist.
 */
final readonly class ExternalViewData
{
    public function __construct(
        public string $id,
        public string $reference,
        public ?string $package,
        public bool $missing,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, reference: string, package: string|null, missing: bool} $data */
        return new self(
            id: $data['id'],
            reference: $data['reference'],
            package: $data['package'],
            missing: $data['missing'],
        );
    }

    /**
     * @return array{id: string, reference: string, package: string|null, missing: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'package' => $this->package,
            'missing' => $this->missing,
        ];
    }
}
