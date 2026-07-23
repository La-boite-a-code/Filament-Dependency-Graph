<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class ResourceData
{
    /**
     * @param  list<string>  $panelIds
     * @param  list<PageData>  $pages
     * @param  list<RelationManagerData>  $relationManagers
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $class,
        public string $shortName,
        public string $modelClass,
        public ?string $modelId,
        public string $label,
        public string $pluralLabel,
        public ?string $navigationGroup,
        public ?string $navigationIcon,
        public array $panelIds,
        public array $pages,
        public array $relationManagers,
        public DiscoveryStatus $status,
        public array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, class: string, short_name: string, model_class: string, model_id: string|null, label: string, plural_label: string, navigation_group: string|null, navigation_icon: string|null, panel_ids: list<string>, pages: list<array{name: string, class: string, type: string|null, url: string|null}>, relation_managers: list<array{class: string, relationship: string|null, related_resource: string|null, title: string|null}>, status: string, warnings: list<string>} $data */
        return new self(
            id: $data['id'],
            class: $data['class'],
            shortName: $data['short_name'],
            modelClass: $data['model_class'],
            modelId: $data['model_id'],
            label: $data['label'],
            pluralLabel: $data['plural_label'],
            navigationGroup: $data['navigation_group'],
            navigationIcon: $data['navigation_icon'],
            panelIds: $data['panel_ids'],
            pages: array_map(
                static fn (array $page): PageData => PageData::fromArray($page),
                $data['pages'],
            ),
            relationManagers: array_map(
                static fn (array $manager): RelationManagerData => RelationManagerData::fromArray($manager),
                $data['relation_managers'],
            ),
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
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
            'label' => $this->label,
            'plural_label' => $this->pluralLabel,
            'navigation_group' => $this->navigationGroup,
            'navigation_icon' => $this->navigationIcon,
            'panel_ids' => $this->panelIds,
            'pages' => array_map(
                static fn (PageData $page): array => $page->toArray(),
                $this->pages,
            ),
            'relation_managers' => array_map(
                static fn (RelationManagerData $manager): array => $manager->toArray(),
                $this->relationManagers,
            ),
            'status' => $this->status->value,
            'warnings' => $this->warnings,
        ];
    }
}
