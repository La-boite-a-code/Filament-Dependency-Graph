<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class ControllerData
{
    /**
     * @param  array<string, ControllerActionData>  $actions  Routed method name to its findings.
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public ?string $file,
        public array $actions,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, file: string|null, actions: array<string, array<string, mixed>>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            file: $data['file'],
            actions: array_map(
                static fn (array $action): ControllerActionData => ControllerActionData::fromArray($action),
                $data['actions'],
            ),
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
            'class' => $this->class,
            'file' => $this->file,
            'actions' => array_map(
                static fn (ControllerActionData $action): array => $action->toArray(),
                $this->actions,
            ),
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
