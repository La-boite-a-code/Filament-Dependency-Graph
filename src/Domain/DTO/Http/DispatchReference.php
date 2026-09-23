<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

/**
 * One statically detected hand-over to the framework: a job dispatch, a mail,
 * a notification or an event.
 */
final readonly class DispatchReference
{
    /**
     * @param  list<string>  $via  Application classes traversed to reach the dispatch.
     */
    public function __construct(
        public string $class,
        public DispatchKind $kind,
        public array $via,
        public string $location,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{class: string, kind: string, via: list<string>, location: string} $data */
        return new self(
            class: $data['class'],
            kind: DispatchKind::from($data['kind']),
            via: $data['via'],
            location: $data['location'],
        );
    }

    /**
     * @return array{class: string, kind: string, via: list<string>, location: string}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'kind' => $this->kind->value,
            'via' => $this->via,
            'location' => $this->location,
        ];
    }

    public function targetId(): string
    {
        return StableIdentifier::dispatchTarget($this->kind, $this->class);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<self>
     */
    public static function listFromArray(array $items): array
    {
        return array_map(static fn (array $item): self => self::fromArray($item), $items);
    }

    /**
     * @param  list<self>  $references
     * @return list<array{class: string, kind: string, via: list<string>, location: string}>
     */
    public static function listToArray(array $references): array
    {
        return array_map(static fn (self $reference): array => $reference->toArray(), $references);
    }
}
