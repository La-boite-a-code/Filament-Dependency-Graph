<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

final readonly class PanelData
{
    /**
     * @param  list<string>  $resourceIds
     */
    public function __construct(
        public string $id,
        public ?string $path,
        public ?string $domain,
        public array $resourceIds,
    ) {}

    /**
     * @param  array{id: string, path: string|null, domain: string|null, resource_ids: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            path: $data['path'],
            domain: $data['domain'],
            resourceIds: $data['resource_ids'],
        );
    }

    /**
     * @return array{id: string, path: string|null, domain: string|null, resource_ids: list<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'domain' => $this->domain,
            'resource_ids' => $this->resourceIds,
        ];
    }
}
