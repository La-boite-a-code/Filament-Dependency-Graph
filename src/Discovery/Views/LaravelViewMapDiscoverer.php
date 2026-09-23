<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use LaBoiteACode\DependencyGraph\Contracts\ViewMapDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
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
 * collects the owners rendering views, then infers the kind of each view
 * from how it is used.
 */
final class LaravelViewMapDiscoverer implements ViewMapDiscoverer
{
    public function __construct(
        private readonly BladeTemplateScanner $scanner,
        private readonly ViewReferenceResolver $resolver,
        private readonly ViewOwnerDiscoverer $owners,
    ) {}

    public function discover(DiscoveryContext $context, array $livewireComponents, HttpMapData $http): ViewMapData
    {
        if (! $context->discoverViews) {
            return new ViewMapData;
        }

        $templates = $this->scanner->templates($context);
        $this->resolver->prepare($templates, $context);

        /** @var list<array{name: string, path: string}> $queue */
        $queue = [];

        foreach ($templates as $name => $path) {
            $queue[] = ['name' => $name, 'path' => $path];
        }

        $views = [];
        $externals = [];
        $dynamics = [];
        $bladeComponentTags = [];
        $extended = [];
        $livewireViews = [];
        $queued = array_fill_keys(array_keys($templates), true);

        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            ['name' => $name, 'path' => $path] = $queue[$cursor];
            $id = StableIdentifier::view($name);
            $contents = is_file($path) ? @file_get_contents($path) : false;

            if ($contents === false) {
                $views[$id] = new ViewData($id, $name, PackagePath::relative($path, $context->basePath), ViewData::KIND_PARTIAL, [], DiscoveryStatus::Partial, ['The template could not be read.']);

                continue;
            }

            if ($this->scanner->isLivewireSingleFile($contents)) {
                $livewireViews[$id] = true;
            }

            $references = [];

            foreach ($this->scanner->references($contents) as $scanned) {
                if ($scanned['type'] === ViewReference::TYPE_DYNAMIC || $scanned['written'] === null) {
                    $dynamic = new DynamicViewData(
                        id: StableIdentifier::dynamicView($name, $scanned['line']),
                        sourceViewId: $id,
                        directive: $scanned['directive'],
                        expression: (string) $scanned['expression'],
                        line: $scanned['line'],
                    );
                    $dynamics[$dynamic->id] = $dynamic;
                    $references[] = new ViewReference(ViewReference::TYPE_DYNAMIC, $dynamic->expression, $scanned['directive'], $dynamic->id, $scanned['line']);

                    continue;
                }

                $resolution = match ($scanned['type']) {
                    ViewReference::TYPE_COMPONENT => $this->resolver->component($scanned['written']),
                    ViewReference::TYPE_LIVEWIRE => $this->resolver->livewire($scanned['written']),
                    default => $this->resolver->view($scanned['written']),
                };

                if ($resolution->kind === ViewResolution::EXTERNAL) {
                    $externals[$resolution->id] ??= new ExternalViewData($resolution->id, $resolution->value, $resolution->package, $resolution->missing);
                }

                if ($resolution->kind === ViewResolution::BLADE_COMPONENT) {
                    $bladeComponentTags[$resolution->value] ??= '<x-' . $scanned['written'] . '>';
                }

                if ($resolution->kind === ViewResolution::PACKAGE_VIEW && $resolution->path !== null && ! isset($queued[$resolution->value])) {
                    $queued[$resolution->value] = true;
                    $queue[] = ['name' => $resolution->value, 'path' => $resolution->path];
                }

                if ($resolution->livewireView) {
                    $livewireViews[$resolution->id] = true;
                }

                if ($scanned['type'] === ViewReference::TYPE_EXTENDS) {
                    $extended[$resolution->id] = true;
                }

                $references[] = new ViewReference($scanned['type'], $scanned['written'], $scanned['directive'], $resolution->id, $scanned['line']);
            }

            $views[$id] = new ViewData($id, $name, PackagePath::relative($path, $context->basePath), ViewData::KIND_PARTIAL, $references, DiscoveryStatus::Complete, []);
        }

        $owners = $this->owners->discover($context, $this->resolver, $livewireComponents, $bladeComponentTags, $http);

        foreach ($owners as $owner) {
            foreach ($owner->renders as $rendered) {
                $resolution = $this->resolver->view($rendered->name);

                if ($resolution->kind === ViewResolution::EXTERNAL) {
                    $externals[$resolution->id] ??= new ExternalViewData($resolution->id, $resolution->value, $resolution->package, $resolution->missing);
                }
            }
        }

        $views = $this->withKinds($views, $owners, $extended, $livewireViews);

        ksort($externals, SORT_STRING);
        ksort($dynamics, SORT_STRING);

        return new ViewMapData(
            views: array_values($views),
            owners: $owners,
            externals: array_values($externals),
            dynamics: array_values($dynamics),
        );
    }

    /**
     * @param  array<string, ViewData>  $views
     * @param  list<ViewOwnerData>  $owners
     * @param  array<string, true>  $extended
     * @param  array<string, true>  $livewireViews
     * @return array<string, ViewData>
     */
    private function withKinds(array $views, array $owners, array $extended, array $livewireViews): array
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

        foreach ($views as $id => $view) {
            $usages = $renderedBy[$id] ?? [];
            $rendered = static fn (array $types, ?string $how = null): bool => array_filter(
                $usages,
                static fn (array $usage): bool => in_array($usage[0], $types, true) && ($how === null || $usage[1] === $how),
            ) !== [];

            $kind = match (true) {
                isset($livewireViews[$id]), $rendered([ViewOwnerData::TYPE_LIVEWIRE], 'render') => ViewData::KIND_LIVEWIRE,
                isset($extended[$id]),
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
