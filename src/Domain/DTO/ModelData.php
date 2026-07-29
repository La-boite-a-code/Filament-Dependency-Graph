<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class ModelData
{
    /**
     * @param  list<string>  $traits
     * @param  array<string, string>  $casts
     * @param  list<string>  $fillable
     * @param  list<string>  $guarded
     * @param  list<string>  $hidden
     * @param  list<string>  $visible
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public string $shortName,
        public string $namespace,
        public string $table,
        public string $connection,
        public ?string $primaryKey,
        public string $keyType,
        public bool $incrementing,
        public bool $timestamps,
        public bool $softDeletes,
        public array $traits,
        public array $casts,
        public array $fillable,
        public array $guarded,
        public array $hidden,
        public array $visible,
        public DiscoveryStatus $status,
        public array $warnings,
        public bool $applicationOwned,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, short_name: string, namespace: string, table: string, connection: string, primary_key: string|null, key_type: string, incrementing: bool, timestamps: bool, soft_deletes: bool, traits: list<string>, casts: array<string, string>, fillable: list<string>, guarded: list<string>, hidden: list<string>, visible: list<string>, status: string, warnings: list<string>, application_owned: bool} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            shortName: $data['short_name'],
            namespace: $data['namespace'],
            table: $data['table'],
            connection: $data['connection'],
            primaryKey: is_string($data['primary_key']) && $data['primary_key'] !== ''
                ? $data['primary_key']
                : null,
            keyType: $data['key_type'],
            incrementing: $data['incrementing'],
            timestamps: $data['timestamps'],
            softDeletes: $data['soft_deletes'],
            traits: $data['traits'],
            casts: $data['casts'],
            fillable: $data['fillable'],
            guarded: $data['guarded'],
            hidden: $data['hidden'],
            visible: $data['visible'],
            status: DiscoveryStatus::from($data['status']),
            warnings: $data['warnings'],
            applicationOwned: $data['application_owned'],
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
            'table' => $this->table,
            'connection' => $this->connection,
            'primary_key' => $this->primaryKey,
            'key_type' => $this->keyType,
            'incrementing' => $this->incrementing,
            'timestamps' => $this->timestamps,
            'soft_deletes' => $this->softDeletes,
            'traits' => $this->traits,
            'casts' => $this->casts,
            'fillable' => $this->fillable,
            'guarded' => $this->guarded,
            'hidden' => $this->hidden,
            'visible' => $this->visible,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
            'application_owned' => $this->applicationOwned,
        ];
    }
}
