<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

/**
 * What one routed controller method validates with, touches and dispatches.
 */
final readonly class ControllerActionData
{
    public const SOURCE_BINDING = 'binding';

    public const SOURCE_TYPE = 'type';

    public const SOURCE_STATIC = 'static';

    /**
     * @param  list<string>  $formRequests
     * @param  array<string, list<string>>  $models  Model class to sources (binding, type, static).
     * @param  list<DispatchReference>  $dispatches
     */
    public function __construct(
        public array $formRequests,
        public array $models,
        public array $dispatches,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{form_requests: list<string>, models: array<string, list<string>>, dispatches: list<array<string, mixed>>} $data */
        return new self(
            formRequests: $data['form_requests'],
            models: $data['models'],
            dispatches: DispatchReference::listFromArray($data['dispatches']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'form_requests' => $this->formRequests,
            'models' => $this->models,
            'dispatches' => DispatchReference::listToArray($this->dispatches),
        ];
    }
}
