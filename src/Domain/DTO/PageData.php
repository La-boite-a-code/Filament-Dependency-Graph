<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

final readonly class PageData
{
    public function __construct(
        public string $name,
        public string $class,
        public ?string $type,
        public ?string $url,
    ) {}

    /**
     * @param  array{name: string, class: string, type: string|null, url: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            class: $data['class'],
            type: $data['type'],
            url: $data['url'],
        );
    }

    /**
     * @return array{name: string, class: string, type: string|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->class,
            'type' => $this->type,
            'url' => $this->url,
        ];
    }
}
