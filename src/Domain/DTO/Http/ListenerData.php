<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class ListenerData
{
    /**
     * @param  array<string, string>  $events  Event class to handling method.
     * @param  list<DispatchReference>  $dispatches
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public ?string $file,
        public array $events,
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
        /** @var array{id: string, class: string, file: string|null, events: array<string, string>, queued: bool, dispatches: list<array<string, mixed>>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            file: $data['file'],
            events: $data['events'],
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
            'class' => $this->class,
            'file' => $this->file,
            'events' => $this->events,
            'queued' => $this->queued,
            'dispatches' => DispatchReference::listToArray($this->dispatches),
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
