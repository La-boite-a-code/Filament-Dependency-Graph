<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Livewire\Component;
use ReflectionProperty;
use Throwable;

/**
 * Resolves a Livewire component name the way the installed Livewire does,
 * without instantiating anything: Livewire 4 through its finder (class,
 * single-file or multi-file component), Livewire 3 through the registry
 * aliases and the class namespace convention. Missing-component resolvers
 * are never called, because they may compile components.
 */
final class LivewireAdapter
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @return array{type: 'class'|'file', value: string}|null
     */
    public function resolve(string $nameOrClass): ?array
    {
        try {
            if ($this->isComponentClass($nameOrClass)) {
                return ['type' => 'class', 'value' => ltrim($nameOrClass, '\\')];
            }

            return $this->container->bound('livewire.finder')
                ? $this->resolveWithFinder($nameOrClass)
                : $this->resolveWithRegistry($nameOrClass);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{type: 'class'|'file', value: string}|null
     */
    private function resolveWithFinder(string $name): ?array
    {
        $finder = $this->container->make('livewire.finder');

        $class = $finder->resolveClassComponentClassName($name);

        if (is_string($class) && $this->isComponentClass($class)) {
            return ['type' => 'class', 'value' => ltrim($class, '\\')];
        }

        $single = $finder->resolveSingleFileComponentPath($name);

        if (is_string($single) && is_file($single)) {
            return ['type' => 'file', 'value' => $single];
        }

        $directory = $finder->resolveMultiFileComponentPath($name);

        if (is_string($directory) && is_dir($directory)) {
            $templates = glob(rtrim($directory, '/\\') . '/*.blade.php') ?: [];

            return $templates === [] ? null : ['type' => 'file', 'value' => $templates[0]];
        }

        return null;
    }

    /**
     * @return array{type: 'class'|'file', value: string}|null
     */
    private function resolveWithRegistry(string $name): ?array
    {
        $registryClass = 'Livewire\\Mechanisms\\ComponentRegistry';

        if (class_exists($registryClass) && $this->container->bound($registryClass)) {
            $registry = $this->container->make($registryClass);

            if (property_exists($registry, 'aliases')) {
                $aliases = (new ReflectionProperty($registry, 'aliases'))->getValue($registry);
                $alias = is_array($aliases) ? ($aliases[$name] ?? null) : null;
                $alias = is_object($alias) ? $alias::class : $alias;

                if (is_string($alias) && $this->isComponentClass($alias)) {
                    return ['type' => 'class', 'value' => ltrim($alias, '\\')];
                }
            }
        }

        $namespace = $this->container->make('config')->get('livewire.class_namespace', 'App\\Livewire');
        $class = trim((string) $namespace, '\\') . '\\' . implode('\\', array_map(
            static fn (string $segment): string => Str::studly($segment),
            explode('.', $name),
        ));

        foreach ([$class, $class . '\\Index'] as $candidate) {
            if ($this->isComponentClass($candidate)) {
                return ['type' => 'class', 'value' => $candidate];
            }
        }

        return null;
    }

    private function isComponentClass(string $class): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, Component::class);
        } catch (Throwable) {
            return false;
        }
    }
}
