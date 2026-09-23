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
        $name = $finder->normalizeName($name) ?? $name;

        $class = $finder->resolveClassComponentClassName($name);

        if (is_string($class) && $this->isComponentClass($class)) {
            return ['type' => 'class', 'value' => ltrim($class, '\\')];
        }

        // Livewire looks for a multi-file component before a single file.
        $directory = $finder->resolveMultiFileComponentPath($name);

        if (is_string($directory) && is_dir($directory)) {
            $directory = rtrim($directory, '/\\');
            $basename = (string) preg_replace('/⚡[\x{FE0E}\x{FE0F}]?/u', '', basename($directory));
            $template = $directory . '/' . $basename . '.blade.php';
            $templates = is_file($template) ? [$template] : (glob($directory . '/*.blade.php') ?: []);

            if ($templates !== []) {
                return ['type' => 'file', 'value' => $templates[0]];
            }
        }

        $single = $finder->resolveSingleFileComponentPath($name);

        return is_string($single) && is_file($single) ? ['type' => 'file', 'value' => $single] : null;
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

        // Volt single-file components live in the Livewire view folder and
        // are resolved at runtime by a resolver that is never called here.
        $viewPath = $this->container->make('config')->get('livewire.view_path');
        $file = is_string($viewPath) ? rtrim($viewPath, '/\\') . '/' . str_replace('.', '/', $name) . '.blade.php' : null;

        return $file !== null && is_file($file) ? ['type' => 'file', 'value' => $file] : null;
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
