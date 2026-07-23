<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\Pages\ViewRecord;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Domain\DTO\PageData;
use Throwable;

final class FilamentPageDiscoverer
{
    public function __construct(
        private readonly FilamentAdapter $adapter,
    ) {}

    /**
     * @param  class-string  $resource
     * @return list<PageData>
     */
    public function discover(string $resource): array
    {
        $pages = [];

        foreach ($this->adapter->resourcePages($resource) as $routeKey => $pageClass) {
            $pages[] = new PageData(
                name: $routeKey,
                class: $pageClass,
                type: $this->pageType($pageClass),
                url: null,
            );
        }

        usort($pages, static fn (PageData $a, PageData $b): int => strcmp($a->name, $b->name));

        return $pages;
    }

    private function pageType(string $pageClass): ?string
    {
        try {
            return match (true) {
                is_subclass_of($pageClass, ListRecords::class) => 'list',
                is_subclass_of($pageClass, CreateRecord::class) => 'create',
                is_subclass_of($pageClass, EditRecord::class) => 'edit',
                is_subclass_of($pageClass, ViewRecord::class) => 'view',
                is_subclass_of($pageClass, ManageRelatedRecords::class) => 'manage-related',
                is_subclass_of($pageClass, ManageRecords::class) => 'manage',
                default => 'custom',
            };
        } catch (Throwable) {
            return null;
        }
    }
}
