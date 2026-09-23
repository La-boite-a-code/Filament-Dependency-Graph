<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use LaBoiteACode\DependencyGraph\Contracts\ViewMapDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Http\ControllerDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Http\RouteDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\DynamicViewData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ExternalViewData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewOwnerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewReference;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

/**
 * Reads every application template once, resolves what it references,
 * collects the owners rendering views, reads the package views reached
 * when they are explored, then infers the kind of each view from how it is
 * used.
 */
final class LaravelViewMapDiscoverer implements CollectsDiscoveryWarnings, ViewMapDiscoverer
{
    /** @var array<string, ViewData> */
    private array $views = [];

    /** @var array<string, DynamicViewData> */
    private array $dynamics = [];

    /** @var array<string, string> Blade component class to the first tag using it. */
    private array $bladeComponentTags = [];

    /** @var array<string, true> */
    private array $extended = [];

    /** @var array<string, true> */
    private array $livewireViews = [];

    public function __construct(
        private readonly BladeTemplateScanner $scanner,
        private readonly ViewReferenceResolver $resolver,
        private readonly ViewOwnerDiscoverer $owners,
        private readonly RouteDiscoverer $routes,
        private readonly ControllerDiscoverer $controllers,
    ) {}

    public function discover(DiscoveryContext $context, array $livewireComponents, HttpMapData $http): ViewMapData
    {
        if (! $context->discoverViews) {
            return new ViewMapData;
        }

        $this->views = [];
        $this->dynamics = [];
        $this->bladeComponentTags = [];
        $this->extended = [];
        $this->livewireViews = [];

        $templates = $this->scanner->templates($context);
        $this->resolver->prepare(
            $templates,
            $context,
            array_map(static fn (LivewireComponentData $component): string => $component->id, $livewireComponents),
        );

        foreach ($templates as $name => $path) {
            $this->scan($name, $path, $context);
        }

        $this->scanPackageViews($context);

        $owners = $this->owners->discover(
            $context,
            $this->resolver,
            $livewireComponents,
            $this->bladeComponentTags,
            $context->discoverHttp ? $http : $this->routesAndControllers($context),
        );

        // Owners may render package views nothing else references.
        $this->scanPackageViews($context);

        $externals = [];

        foreach ($this->resolver->externals() as $id => $resolution) {
            $externals[$id] = new ExternalViewData($id, $resolution->value, $resolution->package, $resolution->missing);
        }

        ksort($externals, SORT_STRING);
        ksort($this->dynamics, SORT_STRING);

        return new ViewMapData(
            views: array_values($this->withKinds($owners)),
            owners: $owners,
            externals: array_values($externals),
            dynamics: array_values($this->dynamics),
        );
    }

    public function pullWarnings(): array
    {
        return $this->scanner->pullWarnings();
    }

    private function scan(string $name, string $path, DiscoveryContext $context): void
    {
        $id = StableIdentifier::view($name);
        $file = PackagePath::relative($path, $context->basePath);
        $contents = is_file($path) ? @file_get_contents($path) : false;

        if ($contents === false) {
            $this->views[$id] = new ViewData($id, $name, $file, ViewData::KIND_PARTIAL, [], DiscoveryStatus::Partial, ['The template could not be read.']);

            return;
        }

        if ($this->scanner->isLivewireSingleFile($contents)) {
            $this->livewireViews[$id] = true;
        }

        $references = [];
        $dynamicOrdinals = [];

        foreach ($this->scanner->references($contents) as $scanned) {
            if ($scanned['type'] === ViewReference::TYPE_DYNAMIC || $scanned['written'] === null) {
                // Several dynamic names may share a line: @includeFirst([$a, $b]).
                $ordinal = $dynamicOrdinals[$scanned['line']] = ($dynamicOrdinals[$scanned['line']] ?? 0) + 1;
                $dynamicId = StableIdentifier::dynamicView($name, $scanned['line']) . ($ordinal > 1 ? '.' . $ordinal : '');

                $this->dynamics[$dynamicId] = new DynamicViewData(
                    id: $dynamicId,
                    sourceViewId: $id,
                    directive: $scanned['directive'],
                    expression: (string) $scanned['expression'],
                    line: $scanned['line'],
                );
                $references[] = new ViewReference(ViewReference::TYPE_DYNAMIC, (string) $scanned['expression'], $scanned['directive'], $dynamicId, $scanned['line']);

                continue;
            }

            $resolution = match ($scanned['type']) {
                ViewReference::TYPE_COMPONENT => $this->resolver->component($scanned['written']),
                ViewReference::TYPE_LIVEWIRE => $this->resolver->livewire($scanned['written']),
                default => $this->resolver->view($scanned['written']),
            };

            if ($resolution->kind === ViewResolution::BLADE_COMPONENT) {
                $this->bladeComponentTags[$resolution->value] ??= str_contains($scanned['written'], '\\')
                    ? $scanned['directive']
                    : '<x-' . $scanned['written'] . '>';
            }

            if ($resolution->livewireView) {
                $this->livewireViews[$resolution->id] = true;
            }

            if ($scanned['type'] === ViewReference::TYPE_EXTENDS) {
                $this->extended[$resolution->id] = true;
            }

            $references[] = new ViewReference($scanned['type'], $scanned['written'], $scanned['directive'], $resolution->id, $scanned['line']);
        }

        $this->views[$id] = new ViewData($id, $name, $file, ViewData::KIND_PARTIAL, $references, DiscoveryStatus::Complete, []);
    }

    /**
     * Reads the package views reached so far, and those they reach in turn.
     */
    private function scanPackageViews(DiscoveryContext $context): void
    {
        do {
            $pending = array_filter(
                $this->resolver->packageViews(),
                fn (ViewResolution $resolution): bool => ! isset($this->views[$resolution->id]) && $resolution->path !== null,
            );

            foreach ($pending as $resolution) {
                $this->scan($resolution->value, (string) $resolution->path, $context);
            }
        } while ($pending !== []);
    }

    /**
     * Without the HTTP map, routes and controllers are still read so that
     * the views they render are not reported as unreferenced.
     */
    private function routesAndControllers(DiscoveryContext $context): HttpMapData
    {
        $routes = $this->routes->discover($context);
        $this->routes->pullWarnings();

        return new HttpMapData(
            routes: $routes,
            controllers: $this->controllers->discover($routes, $context, []),
        );
    }

    /**
     * @param  list<ViewOwnerData>  $owners
     * @return array<string, ViewData>
     */
    private function withKinds(array $owners): array
    {
        $renderedBy = [];

        foreach ($owners as $owner) {
            foreach ($owner->renders as $rendered) {
                // Only a Filament page makes its view a page; widgets and
                // fields render fragments.
                $type = $owner->ownerType === ViewOwnerData::TYPE_FILAMENT && $owner->detail === 'Page'
                    ? ViewOwnerData::TYPE_ROUTE
                    : $owner->ownerType;
                $renderedBy[$rendered->targetId][] = [$type, $rendered->how];
            }
        }

        $views = $this->views;

        foreach ($views as $id => $view) {
            $usages = $renderedBy[$id] ?? [];
            $rendered = static fn (array $types, ?string $how = null): bool => array_filter(
                $usages,
                static fn (array $usage): bool => in_array($usage[0], $types, true) && ($how === null || $usage[1] === $how),
            ) !== [];

            $kind = match (true) {
                isset($this->livewireViews[$id]), $rendered([ViewOwnerData::TYPE_LIVEWIRE], 'render') => ViewData::KIND_LIVEWIRE,
                isset($this->extended[$id]),
                $rendered([ViewOwnerData::TYPE_LIVEWIRE], 'layout'),
                preg_match('/(^|\.)layouts?(\.|$)/', $view->name) === 1 => ViewData::KIND_LAYOUT,
                str_starts_with($view->name, 'components.') => ViewData::KIND_COMPONENT,
                $rendered([ViewOwnerData::TYPE_MAILABLE, ViewOwnerData::TYPE_NOTIFICATION]) => ViewData::KIND_MAIL,
                $rendered([ViewOwnerData::TYPE_ROUTE, ViewOwnerData::TYPE_CONTROLLER]) => ViewData::KIND_PAGE,
                default => ViewData::KIND_PARTIAL,
            };

            $views[$id] = $view->withKind($kind);
        }

        ksort($views, SORT_STRING);

        return $views;
    }
}
