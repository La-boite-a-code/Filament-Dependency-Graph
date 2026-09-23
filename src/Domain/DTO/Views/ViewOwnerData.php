<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

/**
 * A class or a route that renders views.
 */
final readonly class ViewOwnerData
{
    public const TYPE_LIVEWIRE = 'livewire';

    public const TYPE_BLADE_COMPONENT = 'blade_component';

    public const TYPE_FILAMENT = 'filament';

    public const TYPE_MAILABLE = 'mailable';

    public const TYPE_NOTIFICATION = 'notification';

    public const TYPE_ROUTE = 'route';

    public const TYPE_CONTROLLER = 'controller';

    /**
     * @param  list<RenderedView>  $renders
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $ownerType,
        public ?string $class,
        public string $label,
        public ?string $detail,
        public ?string $file,
        public array $renders,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, owner_type: string, class: string|null, label: string, detail: string|null, file: string|null, renders: list<array<string, mixed>>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            ownerType: $data['owner_type'],
            class: $data['class'],
            label: $data['label'],
            detail: $data['detail'],
            file: $data['file'],
            renders: array_map(static fn (array $rendered): RenderedView => RenderedView::fromArray($rendered), $data['renders']),
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
            'owner_type' => $this->ownerType,
            'class' => $this->class,
            'label' => $this->label,
            'detail' => $this->detail,
            'file' => $this->file,
            'renders' => array_map(static fn (RenderedView $rendered): array => $rendered->toArray(), $this->renders),
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
