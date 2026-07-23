<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Filament\Panel;
use LaBoiteACode\DependencyGraph\Compatibility\FilamentAdapter;
use LaBoiteACode\DependencyGraph\Contracts\PanelDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\PanelData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use Throwable;

final class FilamentPanelDiscoverer implements CollectsDiscoveryWarnings, PanelDiscoverer
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly FilamentAdapter $adapter,
    ) {}

    public function discover(DiscoveryContext $context): array
    {
        $panels = [];

        foreach ($this->adapter->panels() as $panel) {
            $data = $this->discoverPanel($panel, $context);

            if ($data !== null) {
                $panels[$data->id] = $data;
            }
        }

        ksort($panels, SORT_STRING);

        return array_values($panels);
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    private function discoverPanel(Panel $panel, DiscoveryContext $context): ?PanelData
    {
        try {
            $id = $panel->getId();

            if ($context->panelIds !== [] && ! in_array($id, $context->panelIds, true)) {
                return null;
            }

            $path = $panel->getPath();
            $domains = $panel->getDomains();

            $resourceIds = array_map(
                static fn (string $resource): string => StableIdentifier::resource($resource),
                array_values($panel->getResources()),
            );

            sort($resourceIds, SORT_STRING);

            return new PanelData(
                id: $id,
                path: $path === '' ? null : $path,
                domain: $domains === [] ? null : (string) $domains[0],
                resourceIds: array_values(array_unique($resourceIds)),
            );
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'panel_discovery_failed',
                message: sprintf('A Filament panel could not be inspected: %s', $exception->getMessage()),
                exceptionClass: $exception::class,
            );

            return null;
        }
    }
}
