<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

final readonly class RelationManagerData
{
    public function __construct(
        public string $class,
        public ?string $relationship,
        public ?string $relatedResource,
        public ?string $title,
    ) {}

    /**
     * @param  array{class: string, relationship: string|null, related_resource: string|null, title: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            class: $data['class'],
            relationship: $data['relationship'],
            relatedResource: $data['related_resource'],
            title: $data['title'],
        );
    }

    /**
     * @return array{class: string, relationship: string|null, related_resource: string|null, title: string|null}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'relationship' => $this->relationship,
            'related_resource' => $this->relatedResource,
            'title' => $this->title,
        ];
    }
}
