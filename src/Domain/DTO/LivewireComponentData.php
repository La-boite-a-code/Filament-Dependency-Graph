<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

final readonly class LivewireComponentData
{
    /**
     * @param  list<string>  $publicProperties
     * @param  list<string>  $publicMethods
     * @param  array<string, list<string>>  $modelReferences  Model class to reference locations.
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public string $shortName,
        public string $namespace,
        public string $alias,
        public ?string $view,
        public ?string $file,
        public array $publicProperties,
        public array $publicMethods,
        public array $modelReferences,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, short_name: string, namespace: string, alias: string, view: string|null, file: string|null, public_properties: list<string>, public_methods: list<string>, model_references: array<string, list<string>>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            shortName: $data['short_name'],
            namespace: $data['namespace'],
            alias: $data['alias'],
            view: $data['view'],
            file: $data['file'],
            publicProperties: $data['public_properties'],
            publicMethods: $data['public_methods'],
            modelReferences: $data['model_references'],
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
            'short_name' => $this->shortName,
            'namespace' => $this->namespace,
            'alias' => $this->alias,
            'view' => $this->view,
            'file' => $this->file,
            'public_properties' => $this->publicProperties,
            'public_methods' => $this->publicMethods,
            'model_references' => $this->modelReferences,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @return list<string>
     */
    public function modelIds(): array
    {
        return array_map(
            static fn (string $class): string => StableIdentifier::model($class),
            array_keys($this->modelReferences),
        );
    }
}
