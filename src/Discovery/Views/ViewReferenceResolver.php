<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use Illuminate\Support\Str;
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

    /** @var array<string, true> Ids of the Livewire components the graph has nodes for. */
    private array $livewireIds = [];

    /** @var array<string, string> Anonymous component path hash to its prefix. */
    private array $anonymousPrefixes = [];

    /** @var array<string, ViewResolution> Every external resolution handed out, by id. */
    private array $externals = [];

    /** @var array<string, ViewResolution> Package views handed out, by view name. */
    private array $packageViews = [];

    public function __construct(
        private readonly Factory $views,
        private readonly BladeCompiler $blade,
        private readonly LivewireAdapter $livewire,
    ) {}

    /**
     * @param  array<string, string>  $templates  Application view name to path.
     * @param  list<string>  $livewireIds  Ids of the discovered Livewire components.
     */
    public function prepare(array $templates, DiscoveryContext $context, array $livewireIds = []): void
    {
        $this->applicationViews = $templates;
        $this->livewireIds = array_fill_keys($livewireIds, true);
        $this->anonymousPrefixes = [];

        foreach ($this->blade->getAnonymousComponentPaths() as $path) {
            if (is_array($path) && is_string($path['prefixHash'] ?? null)) {
                $this->anonymousPrefixes[$path['prefixHash']] = is_string($path['prefix'] ?? null) ? $path['prefix'] : 'anonymous';
            }
        }

        $this->applicationPaths = [];
        $this->memo = [];
        $this->externals = [];
        $this->packageViews = [];
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
        return $this->record($this->memo['view|' . $name] ??= $this->resolveView($name));
    }

    public function component(string $tag): ViewResolution
    {
        return $this->record($this->memo['component|' . $tag] ??= $this->resolveComponent($tag));
    }

    public function livewire(string $nameOrClass): ViewResolution
    {
        return $this->record($this->memo['livewire|' . $nameOrClass] ??= $this->resolveLivewire($nameOrClass));
    }

    /**
     * The view Livewire renders for a component without a render() method:
     * the file named after the component under the Livewire view path.
     */
    public function livewireConventionView(string $alias): ?ViewResolution
    {
        $base = config('livewire.view_path', resource_path('views/livewire'));

        if (! is_string($base) || $alias === '') {
            return null;
        }

        $name = $this->applicationPaths[$this->realPath(rtrim($base, '/\\') . '/' . str_replace('.', '/', $alias) . '.blade.php')] ?? null;

        return $name === null ? null : $this->view($name);
    }

    /**
     * External views referenced so far, templates and owners alike.
     *
     * @return array<string, ViewResolution>
     */
    public function externals(): array
    {
        return $this->externals;
    }

    /**
     * Package views referenced so far, to be read when they are explored.
     *
     * @return array<string, ViewResolution>
     */
    public function packageViews(): array
    {
        return $this->packageViews;
    }

    private function record(ViewResolution $resolution): ViewResolution
    {
        if ($resolution->kind === ViewResolution::EXTERNAL) {
            $this->externals[$resolution->id] = $resolution;
        }

        if ($resolution->kind === ViewResolution::PACKAGE_VIEW) {
            $this->packageViews[$resolution->value] = $resolution;
        }

        return $resolution;
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

        $excluded = $this->context->excludedViews ?? [];

        if ($excluded !== [] && Str::is($excluded, $name)) {
            return $this->external($name, 'excluded');
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
        // @component(Alert::class) names the class itself.
        if (str_contains($tag, '\\') && $this->classExists($tag)) {
            return $this->componentClassResolution(ltrim($tag, '\\'));
        }

        try {
            $resolved = $this->tags?->componentClass($tag);
        } catch (Throwable) {
            return $this->external('x-' . $tag, $this->package($tag), missing: true);
        }

        if (! is_string($resolved)) {
            return $this->external('x-' . $tag, $this->package($tag), missing: true);
        }

        if ($this->classExists($resolved)) {
            return $this->componentClassResolution(ltrim($resolved, '\\'));
        }

        $resolution = $this->view($resolved);
        $readable = $this->withAnonymousPrefix($resolved);

        // The finder only knows the hashed namespace; keep it to resolve,
        // show the prefix the application chose.
        return $resolution->kind === ViewResolution::EXTERNAL && $readable !== $resolved
            ? $this->external($readable, $this->package($readable), $resolution->missing)
            : $resolution;
    }

    private function componentClassResolution(string $class): ViewResolution
    {
        if (NamespaceMatcher::matchesNamespace($class, $this->context->httpApplicationNamespaces ?? [])) {
            return new ViewResolution(ViewResolution::BLADE_COMPONENT, StableIdentifier::bladeComponent($class), $class);
        }

        return $this->external($class, strtolower(explode('\\', $class)[0]));
    }

    /**
     * Anonymous component paths are registered under a hash: show the
     * prefix the application chose instead.
     */
    private function withAnonymousPrefix(string $name): string
    {
        if (! str_contains($name, '::')) {
            return $name;
        }

        [$namespace, $view] = explode('::', $name, 2);

        return isset($this->anonymousPrefixes[$namespace]) ? $this->anonymousPrefixes[$namespace] . '::' . $view : $name;
    }

    private function resolveLivewire(string $nameOrClass): ViewResolution
    {
        $resolved = $this->livewire->resolve($nameOrClass);

        if ($resolved === null) {
            return $this->external('livewire:' . $nameOrClass, $this->package($nameOrClass), missing: true);
        }

        if ($resolved['type'] === 'class') {
            $class = $resolved['value'];
            $id = StableIdentifier::livewireComponent($class);

            // Only components discovered as nodes are linked; any other class
            // (outside the Livewire paths, Filament widgets…) stays visible
            // as a leaf instead of silently losing its edge.
            return isset($this->livewireIds[$id])
                ? new ViewResolution(ViewResolution::LIVEWIRE, $id, $class)
                : $this->external($class, NamespaceMatcher::matchesNamespace($class, $this->context->httpApplicationNamespaces ?? []) ? null : strtolower(explode('\\', $class)[0]));
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
