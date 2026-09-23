<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class EventData
{
    /**
     * @param  list<string>  $listenerClasses
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public ?string $file,
        public array $listenerClasses,
        public int $closureListenerCount,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, file: string|null, listener_classes: list<string>, closure_listener_count: int, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            file: $data['file'],
            listenerClasses: $data['listener_classes'],
            closureListenerCount: $data['closure_listener_count'],
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
            'listener_classes' => $this->listenerClasses,
            'closure_listener_count' => $this->closureListenerCount,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
