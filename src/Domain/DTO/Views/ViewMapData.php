<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Views;

/**
 * Everything the Views scope shows: templates, the classes and routes that
 * render them, and the package or dynamic views they reach.
 */
final readonly class ViewMapData
{
    /**
     * @param  list<ViewData>  $views
     * @param  list<ViewOwnerData>  $owners
     * @param  list<ExternalViewData>  $externals
     * @param  list<DynamicViewData>  $dynamics
     */
    public function __construct(
        public array $views = [],
        public array $owners = [],
        public array $externals = [],
        public array $dynamics = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{views?: list<array<string, mixed>>, owners?: list<array<string, mixed>>, externals?: list<array<string, mixed>>, dynamics?: list<array<string, mixed>>} $data */
        return new self(
            views: array_map(static fn (array $item): ViewData => ViewData::fromArray($item), $data['views'] ?? []),
            owners: array_map(static fn (array $item): ViewOwnerData => ViewOwnerData::fromArray($item), $data['owners'] ?? []),
            externals: array_map(static fn (array $item): ExternalViewData => ExternalViewData::fromArray($item), $data['externals'] ?? []),
            dynamics: array_map(static fn (array $item): DynamicViewData => DynamicViewData::fromArray($item), $data['dynamics'] ?? []),
        );
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [
            'views' => array_map(static fn (ViewData $item): array => $item->toArray(), $this->views),
            'owners' => array_map(static fn (ViewOwnerData $item): array => $item->toArray(), $this->owners),
            'externals' => array_map(static fn (ExternalViewData $item): array => $item->toArray(), $this->externals),
            'dynamics' => array_map(static fn (DynamicViewData $item): array => $item->toArray(), $this->dynamics),
        ];
    }
}
