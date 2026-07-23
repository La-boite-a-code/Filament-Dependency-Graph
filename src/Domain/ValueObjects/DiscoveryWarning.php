<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\ValueObjects;

final readonly class DiscoveryWarning
{
    public function __construct(
        public string $type,
        public string $message,
        public ?string $class = null,
        public ?string $method = null,
        public ?string $exceptionClass = null,
    ) {}

    /**
     * @param  array{type: string, message: string, class?: string|null, method?: string|null, exception_class?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            message: $data['message'],
            class: $data['class'] ?? null,
            method: $data['method'] ?? null,
            exceptionClass: $data['exception_class'] ?? null,
        );
    }

    /**
     * @return array{type: string, message: string, class: string|null, method: string|null, exception_class: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'class' => $this->class,
            'method' => $this->method,
            'exception_class' => $this->exceptionClass,
        ];
    }
}
