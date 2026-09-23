<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Illuminate\View\Factory;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use Throwable;

/**
 * Resolves names written in templates or rendered by classes with the
 * framework's own resolvers: the view finder for view names, the Blade
 * component tag compiler for <x-…> tags, the Livewire registry for
 * Livewire components. Only files and class existence are checked.
 */
final class ViewReferenceResolver
{
    /** @var array<string, string> Real path of an application template to its view name. */
    private array $applicationPaths = [];

    /** @var array<string, string> */
    private array $applicationViews = [];

    /** @var array<string, ViewResolution> */
    private array $memo = [];

    private ?DiscoveryContext $context = null;

    private ?ComponentTagCompiler $tags = null;

    public function __construct(
        private readonly Factory $views,
        private readonly BladeCompiler $blade,
        private readonly LivewireAdapter $livewire,
    ) {}

    /**
     * @param  array<string, string>  $templates  Application view name to path.
     */
    public function prepare(array $templates, DiscoveryContext $context): void
    {
        $this->applicationViews = $templates;
        $this->applicationPaths = [];
        $this->memo = [];
        $this->context = $context;
        $this->tags = new ComponentTagCompiler(
            $this->blade->getClassComponentAliases(),
            $this->blade->getClassComponentNamespaces(),
            $this->blade,
        );

        foreach ($templates as $name => $path) {
            $this->applicationPaths[$this->realPath($path)] = $name;
        }
    }

    public function view(string $name): ViewResolution
    {
        return $this->memo['view|' . $name] ??= $this->resolveView($name);
    }

    public function component(string $tag): ViewResolution
    {
        return $this->memo['component|' . $tag] ??= $this->resolveComponent($tag);
    }

    public function livewire(string $nameOrClass): ViewResolution
    {
        return $this->memo['livewire|' . $nameOrClass] ??= $this->resolveLivewire($nameOrClass);
    }

    private function resolveView(string $name): ViewResolution
    {
        if (isset($this->applicationViews[$name])) {
            return new ViewResolution(ViewResolution::APPLICATION_VIEW, StableIdentifier::view($name), $name, $this->applicationViews[$name]);
        }

        // Markdown mail components are only registered while a mail renders.
        if (str_starts_with($name, 'mail::')) {
            return $this->external($name, 'mail');
        }

        try {
            $path = $this->views->getFinder()->find($name);
        } catch (Throwable) {
            return $this->external($name, $this->package($name), missing: true);
        }

        $applicationName = $this->applicationPaths[$this->realPath($path)] ?? null;

        if ($applicationName !== null) {
            return new ViewResolution(ViewResolution::APPLICATION_VIEW, StableIdentifier::view($applicationName), $applicationName, $path);
        }

        if ($this->context?->explorePackageViews === true) {
            return new ViewResolution(ViewResolution::PACKAGE_VIEW, StableIdentifier::view($name), $name, $path, $this->package($name));
        }

        return $this->external($name, $this->package($name));
    }

    private function resolveComponent(string $tag): ViewResolution
    {
        try {
            $resolved = $this->tags?->componentClass($tag);
        } catch (Throwable) {
            return $this->external('x-' . $tag, $this->package($tag), missing: true);
        }

        if (! is_string($resolved)) {
            return $this->external('x-' . $tag, $this->package($tag), missing: true);
        }

        if ($this->classExists($resolved)) {
            $class = ltrim($resolved, '\\');

            if (NamespaceMatcher::matchesNamespace($class, $this->context->httpApplicationNamespaces ?? [])) {
                return new ViewResolution(ViewResolution::BLADE_COMPONENT, StableIdentifier::bladeComponent($class), $class);
            }

            return $this->external($class, strtolower(explode('\\', $class)[0]));
        }

        return $this->view($resolved);
    }

    private function resolveLivewire(string $nameOrClass): ViewResolution
    {
        $resolved = $this->livewire->resolve($nameOrClass);

        if ($resolved === null) {
            return $this->external('livewire:' . $nameOrClass, $this->package($nameOrClass), missing: true);
        }

        if ($resolved['type'] === 'class') {
            $class = $resolved['value'];
            $namespaces = [...($this->context->livewireNamespaces ?? []), ...($this->context->httpApplicationNamespaces ?? [])];

            return NamespaceMatcher::matchesNamespace($class, $namespaces)
                ? new ViewResolution(ViewResolution::LIVEWIRE, StableIdentifier::livewireComponent($class), $class)
                : $this->external($class, strtolower(explode('\\', $class)[0]));
        }

        $applicationName = $this->applicationPaths[$this->realPath($resolved['value'])] ?? null;

        return $applicationName === null
            ? $this->external('livewire:' . $nameOrClass, $this->package($nameOrClass))
            : new ViewResolution(
                ViewResolution::APPLICATION_VIEW,
                StableIdentifier::view($applicationName),
                $applicationName,
                $resolved['value'],
                livewireView: true,
            );
    }

    private function external(string $reference, ?string $package, bool $missing = false): ViewResolution
    {
        return new ViewResolution(
            ViewResolution::EXTERNAL,
            StableIdentifier::externalView($reference),
            $reference,
            package: $package,
            missing: $missing,
        );
    }

    private function package(string $reference): ?string
    {
        return str_contains($reference, '::') ? explode('::', $reference, 2)[0] : null;
    }

    private function classExists(string $class): bool
    {
        try {
            return class_exists($class);
        } catch (Throwable) {
            return false;
        }
    }

    private function realPath(string $path): string
    {
        return realpath($path) ?: $path;
    }
}
