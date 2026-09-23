<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

/**
 * An application Blade template.
 */
final readonly class ViewData
{
    public const KIND_LAYOUT = 'layout';

    public const KIND_PAGE = 'page';

    public const KIND_PARTIAL = 'partial';

    public const KIND_COMPONENT = 'component';

    public const KIND_LIVEWIRE = 'livewire';

    public const KIND_MAIL = 'mail';

    /**
     * @param  list<ViewReference>  $references
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $file,
        public string $kind,
        public array $references,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    public function withKind(string $kind): self
    {
        return new self($this->id, $this->name, $this->file, $kind, $this->references, $this->status, $this->warnings);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, name: string, file: string|null, kind: string, references: list<array<string, mixed>>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            name: $data['name'],
            file: $data['file'],
            kind: $data['kind'],
            references: array_map(static fn (array $reference): ViewReference => ViewReference::fromArray($reference), $data['references']),
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
            'name' => $this->name,
            'file' => $this->file,
            'kind' => $this->kind,
            'references' => array_map(static fn (ViewReference $reference): array => $reference->toArray(), $this->references),
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
