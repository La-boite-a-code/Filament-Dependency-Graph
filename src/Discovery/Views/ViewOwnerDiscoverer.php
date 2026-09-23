<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\View\Component as BladeComponent;
use LaBoiteACode\DependencyGraph\Discovery\ClassCandidateFinder;
use LaBoiteACode\DependencyGraph\Discovery\Support\SourceScanner;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\RenderedView;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewOwnerData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Finds the classes and routes that render views, and which views, without
 * instantiating anything: literal view names in their methods, the
 * default value of Filament $view properties, Livewire layout attributes.
 */
final class ViewOwnerDiscoverer
{
    private const LAYOUT_ATTRIBUTE = 'Livewire\\Attributes\\Layout';

    public function __construct(
        private readonly SourceScanner $scanner,
        private readonly ClassCandidateFinder $candidates,
    ) {}

    /**
     * @param  list<LivewireComponentData>  $livewireComponents
     * @param  array<string, string>  $bladeComponentTags  Blade component class to the first tag using it.
     * @return list<ViewOwnerData>
     */
    public function discover(
        DiscoveryContext $context,
        ViewReferenceResolver $resolver,
        array $livewireComponents,
        array $bladeComponentTags,
        HttpMapData $http,
    ): array {
        $owners = [];

        foreach ($livewireComponents as $component) {
            $owners[] = $this->livewire($component, $context, $resolver);
        }

        foreach ($this->bladeComponentClasses($context, $bladeComponentTags) as $class) {
            $owners[] = $this->classOwner(
                $class,
                ViewOwnerData::TYPE_BLADE_COMPONENT,
                StableIdentifier::bladeComponent($class),
                $bladeComponentTags[$class] ?? null,
                ['render' => 'render'],
                $context,
                $resolver,
            );
        }

        foreach ($this->filamentClasses($context) as $class) {
            $owners[] = $this->filament($class, $context, $resolver);
        }

        foreach ($this->mailClasses($context, $http) as $class => $kind) {
            $owners[] = $this->classOwner(
                $class,
                $kind === DispatchKind::Mailable ? ViewOwnerData::TYPE_MAILABLE : ViewOwnerData::TYPE_NOTIFICATION,
                StableIdentifier::dispatchTarget($kind, $class),
                $kind->value,
                ['content' => null, 'build' => null, 'toMail' => null],
                $context,
                $resolver,
            );
        }

        foreach ($http->routes as $route) {
            if ($route->view !== null) {
                $target = $resolver->view($route->view);
                $owners[] = new ViewOwnerData(
                    id: $route->id,
                    ownerType: ViewOwnerData::TYPE_ROUTE,
                    class: null,
                    label: $route->label(),
                    detail: $route->name,
                    file: null,
                    renders: [new RenderedView($target->id, $route->view, 'Route::view', null)],
                    status: DiscoveryStatus::Complete,
                    warnings: [],
                );
            }
        }

        foreach ($http->controllers as $controller) {
            $renders = [];

            foreach ($controller->actions as $method => $action) {
                foreach ($action->views as $name) {
                    $renders[] = new RenderedView($resolver->view($name)->id, $name, 'view', (string) $method);
                }
            }

            if ($renders !== []) {
                $owners[] = new ViewOwnerData(
                    id: $controller->id,
                    ownerType: ViewOwnerData::TYPE_CONTROLLER,
                    class: $controller->class,
                    label: ClassName::shortName($controller->class),
                    detail: null,
                    file: $controller->file,
                    renders: $renders,
                    status: DiscoveryStatus::Complete,
                    warnings: [],
                );
            }
        }

        // Blade components stay even without a view: tags point to them.
        $owners = array_filter(
            $owners,
            static fn (ViewOwnerData $owner): bool => $owner->renders !== [] || $owner->ownerType === ViewOwnerData::TYPE_BLADE_COMPONENT,
        );

        usort($owners, static fn (ViewOwnerData $a, ViewOwnerData $b): int => strcmp($a->id, $b->id));

        return $owners;
    }

    private function livewire(LivewireComponentData $component, DiscoveryContext $context, ViewReferenceResolver $resolver): ViewOwnerData
    {
        $renders = [];
        $warnings = [];

        try {
            if (! class_exists($component->class)) {
                throw new RuntimeException('class not found');
            }

            $class = new ReflectionClass($component->class);

            foreach ($this->literals($class, 'render') as $literal) {
                $how = $literal['how'] === 'layout' ? 'layout' : 'render';
                $renders[] = new RenderedView($resolver->view($literal['name'])->id, $literal['name'], $how, 'render');
            }

            $attributes = $class->getAttributes(self::LAYOUT_ATTRIBUTE);

            if ($class->hasMethod('render')) {
                $attributes = [...$attributes, ...$class->getMethod('render')->getAttributes(self::LAYOUT_ATTRIBUTE)];
            }

            foreach ($attributes as $attribute) {
                $layout = $attribute->getArguments()[0] ?? $attribute->getArguments()['name'] ?? null;

                if (is_string($layout)) {
                    $renders[] = new RenderedView($resolver->view($layout)->id, $layout, 'layout', null);
                }
            }
        } catch (Throwable $exception) {
            $warnings[] = sprintf('The rendered views could not be read: %s', $exception->getMessage());
        }

        return new ViewOwnerData(
            id: $component->id,
            ownerType: ViewOwnerData::TYPE_LIVEWIRE,
            class: $component->class,
            label: $component->shortName,
            detail: $component->alias,
            file: $component->file,
            renders: $this->unique($renders),
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * @param  class-string  $class
     */
    private function filament(string $class, DiscoveryContext $context, ViewReferenceResolver $resolver): ViewOwnerData
    {
        $renders = [];
        $warnings = [];
        $reflection = new ReflectionClass($class);

        try {
            $property = $reflection->hasProperty('view') ? $reflection->getProperty('view') : null;

            if (
                $property !== null
                && $property->getDeclaringClass()->getName() === $class
                && $property->hasDefaultValue()
                && is_string($property->getDefaultValue())
            ) {
                $view = $property->getDefaultValue();
                $renders[] = new RenderedView($resolver->view($view)->id, $view, '$view', null);
            } elseif ($reflection->hasMethod('getView') && $reflection->getMethod('getView')->getDeclaringClass()->getName() === $class) {
                $source = $this->scanner->method($reflection->getMethod('getView'));
                $names = [
                    ...array_column($source?->viewLiterals() ?? [], 'name'),
                    ...array_column($source?->returnedStringLiterals() ?? [], 'name'),
                ];

                foreach (array_unique($names) as $name) {
                    $renders[] = new RenderedView($resolver->view($name)->id, $name, 'getView', 'getView');
                }
            }
        } catch (Throwable $exception) {
            $warnings[] = sprintf('The view could not be read: %s', $exception->getMessage());
        }

        return new ViewOwnerData(
            id: StableIdentifier::filamentComponent($class),
            ownerType: ViewOwnerData::TYPE_FILAMENT,
            class: $class,
            label: ClassName::shortName($class),
            detail: $this->filamentKind($reflection),
            file: $this->file($reflection, $context),
            renders: $renders,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * @param  array<string, string|null>  $methods  Method name to the "how" of its views, null to keep the literal kind.
     */
    private function classOwner(
        string $class,
        string $type,
        string $id,
        ?string $detail,
        array $methods,
        DiscoveryContext $context,
        ViewReferenceResolver $resolver,
    ): ViewOwnerData {
        $renders = [];
        $warnings = [];
        $file = null;

        try {
            if (! class_exists($class)) {
                throw new RuntimeException('class not found');
            }

            $reflection = new ReflectionClass($class);
            $file = $this->file($reflection, $context);

            foreach ($methods as $method => $how) {
                foreach ($this->literals($reflection, $method) as $literal) {
                    $renders[] = new RenderedView($resolver->view($literal['name'])->id, $literal['name'], $how ?? $literal['how'], $method);
                }
            }
        } catch (Throwable $exception) {
            $warnings[] = sprintf('The rendered views could not be read: %s', $exception->getMessage());
        }

        return new ViewOwnerData(
            id: $id,
            ownerType: $type,
            class: $class,
            label: ClassName::shortName($class),
            detail: $detail,
            file: $file,
            renders: $this->unique($renders),
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * Literal views of a method declared by the class itself.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<array{name: string, how: string, line: int}>
     */
    private function literals(ReflectionClass $class, string $method): array
    {
        if (! $class->hasMethod($method)) {
            return [];
        }

        $reflection = $class->getMethod($method);

        if ($reflection->getDeclaringClass()->getName() !== $class->getName() && ! $this->isApplicationClass($reflection->getDeclaringClass()->getName())) {
            return [];
        }

        return $this->scanner->method($reflection)?->viewLiterals() ?? [];
    }

    /**
     * @param  array<string, string>  $tags
     * @return list<string>
     */
    private function bladeComponentClasses(DiscoveryContext $context, array $tags): array
    {
        $classes = array_keys($tags);

        foreach ($this->candidates->fromPaths($context->bladeComponentPaths) as $class) {
            if ($this->isConcreteSubclass($class, BladeComponent::class)) {
                $classes[] = ClassName::normalize($class);
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private function filamentClasses(DiscoveryContext $context): array
    {
        $classes = [];

        foreach ($this->candidates->fromPaths($context->filamentViewPaths) as $class) {
            try {
                if (! class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                $parent = $reflection->getParentClass();

                while ($parent !== false && ! str_starts_with($parent->getName(), 'Filament\\')) {
                    $parent = $parent->getParentClass();
                }

                if (! $reflection->isAbstract() && $parent !== false) {
                    $classes[] = $reflection->getName();
                }
            } catch (Throwable) {
                continue;
            }
        }

        sort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @return array<string, DispatchKind>
     */
    private function mailClasses(DiscoveryContext $context, HttpMapData $http): array
    {
        $classes = [];

        foreach ($http->dispatchables as $dispatchable) {
            if ($dispatchable->kind === DispatchKind::Mailable || $dispatchable->kind === DispatchKind::Notification) {
                $classes[$dispatchable->class] = $dispatchable->kind;
            }
        }

        foreach ($this->candidates->fromPaths($context->mailPaths) as $class) {
            $class = ClassName::normalize($class);

            if ($this->isConcreteSubclass($class, Mailable::class)) {
                $classes[$class] ??= DispatchKind::Mailable;
            } elseif ($this->isConcreteSubclass($class, Notification::class)) {
                $classes[$class] ??= DispatchKind::Notification;
            }
        }

        ksort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private function filamentKind(ReflectionClass $class): string
    {
        for ($parent = $class->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            $name = $parent->getName();

            $kind = match (true) {
                str_contains($name, '\\Pages\\') => 'Page',
                str_contains($name, '\\Widgets\\') => 'Widget',
                str_contains($name, '\\Forms\\') => 'Field',
                str_contains($name, '\\Infolists\\') => 'Entry',
                str_contains($name, '\\Tables\\') => 'Column',
                str_contains($name, '\\Actions\\') => 'Action',
                default => null,
            };

            if ($kind !== null) {
                return $kind;
            }
        }

        return 'Component';
    }

    private function isConcreteSubclass(string $class, string $parent): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, $parent) && ! (new ReflectionClass($class))->isAbstract();
        } catch (Throwable) {
            return false;
        }
    }

    private function isApplicationClass(string $class): bool
    {
        return ! str_starts_with($class, 'Illuminate\\')
            && ! str_starts_with($class, 'Filament\\')
            && ! str_starts_with($class, 'Livewire\\');
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private function file(ReflectionClass $class, DiscoveryContext $context): ?string
    {
        $path = $class->getFileName();

        return is_string($path) ? PackagePath::relative($path, $context->basePath) : null;
    }

    /**
     * @param  list<RenderedView>  $renders
     * @return list<RenderedView>
     */
    private function unique(array $renders): array
    {
        $unique = [];

        foreach ($renders as $rendered) {
            $unique[$rendered->targetId . '|' . $rendered->how . '|' . $rendered->method] ??= $rendered;
        }

        return array_values($unique);
    }
}
