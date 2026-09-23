<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;

/**
 * A job, mailable or notification reached through a dispatch.
 */
final readonly class DispatchableData
{
    /**
     * @param  list<DispatchReference>  $dispatches  Only jobs are scanned for further dispatches.
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public DispatchKind $kind,
        public string $class,
        public ?string $file,
        public bool $queued,
        public array $dispatches,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, kind: string, class: string, file: string|null, queued: bool, dispatches: list<array<string, mixed>>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            kind: DispatchKind::from($data['kind']),
            class: $data['class'],
            file: $data['file'],
            queued: $data['queued'],
            dispatches: DispatchReference::listFromArray($data['dispatches']),
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
            'kind' => $this->kind->value,
            'class' => $this->class,
            'file' => $this->file,
            'queued' => $this->queued,
            'dispatches' => DispatchReference::listToArray($this->dispatches),
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
