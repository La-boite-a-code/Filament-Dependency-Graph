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
     * @param  list<string>  $views  View names the action renders, found statically.
     */
    public function __construct(
        public array $formRequests,
        public array $models,
        public array $dispatches,
        public array $views = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{form_requests: list<string>, models: array<string, list<string>>, dispatches: list<array<string, mixed>>, views?: list<string>} $data */
        return new self(
            formRequests: $data['form_requests'],
            models: $data['models'],
            dispatches: DispatchReference::listFromArray($data['dispatches']),
            views: $data['views'] ?? [],
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
            'views' => $this->views,
        ];
    }
}
