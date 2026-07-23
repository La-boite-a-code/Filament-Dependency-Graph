<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

final readonly class InspectionSection
{
    /**
     * Entries map a human readable label to a displayable value. Values stay
     * scalar, null, or lists of scalars so the section can be rendered by any
     * presentation layer and serialized deterministically.
     *
     * @param  array<string, scalar|list<scalar>|null>  $entries
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $entries,
    ) {}

    /**
     * @return array{key: string, title: string, entries: array<string, scalar|list<scalar>|null>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'entries' => $this->entries,
        ];
    }
}
