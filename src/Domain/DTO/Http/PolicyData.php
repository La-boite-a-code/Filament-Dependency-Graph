<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class PolicyData
{
    public const SOURCE_REGISTERED = 'registered';

    public const SOURCE_ATTRIBUTE = 'attribute';

    public const SOURCE_CONVENTION = 'convention';

    /**
     * @param  list<string>  $abilities
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public ?string $file,
        public string $modelClass,
        public array $abilities,
        public string $source,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, file: string|null, model_class: string, abilities: list<string>, source: string, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            file: $data['file'],
            modelClass: $data['model_class'],
            abilities: $data['abilities'],
            source: $data['source'],
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
            'model_class' => $this->modelClass,
            'abilities' => $this->abilities,
            'source' => $this->source,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
