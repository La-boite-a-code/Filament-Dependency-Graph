<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class FormRequestData
{
    /**
     * @param  array<string, list<string>>|null  $rules  Field to normalized rules, null when not read.
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public ?string $file,
        public bool $hasAuthorize,
        public bool $hasRules,
        public ?array $rules,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, file: string|null, has_authorize: bool, has_rules: bool, rules: array<string, list<string>>|null, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            file: $data['file'],
            hasAuthorize: $data['has_authorize'],
            hasRules: $data['has_rules'],
            rules: $data['rules'],
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
            'has_authorize' => $this->hasAuthorize,
            'has_rules' => $this->hasRules,
            'rules' => $this->rules,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
