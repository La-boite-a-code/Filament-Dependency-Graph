<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

/**
 * Middleware of a route without running it: the route definition, the
 * framework's controller middleware attributes, then groups expanded and
 * aliases paired with their classes so filters match however the
 * middleware was written.
 */
final class MiddlewareResolver
{
    private const MIDDLEWARE_ATTRIBUTE = 'Illuminate\\Routing\\Attributes\\Controllers\\Middleware';

    private const WITHOUT_MIDDLEWARE_ATTRIBUTE = 'Illuminate\\Routing\\Attributes\\Controllers\\WithoutMiddleware';

    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * @return array{declared: list<string>, resolved: list<string>}
     */
    public function resolve(Route $route, ?string $controllerClass, ?string $controllerMethod): array
    {
        $excluded = [
            ...$this->names($route->excludedMiddleware()),
            ...$this->attributes($controllerClass, $controllerMethod, self::WITHOUT_MIDDLEWARE_ATTRIBUTE),
        ];

        $declared = array_values(array_diff(array_values(array_unique([
            ...$this->names($route->middleware()),
            ...$this->attributes($controllerClass, $controllerMethod, self::MIDDLEWARE_ATTRIBUTE),
        ])), $excluded));

        $resolved = [];
        $this->expand($declared, $resolved, []);

        return [
            'declared' => $declared,
            'resolved' => array_values(array_diff(array_keys($resolved), $excluded)),
        ];
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, true>  $resolved
     * @param  array<string, true>  $visitedGroups
     */
    private function expand(array $names, array &$resolved, array $visitedGroups): void
    {
        $groups = $this->router->getMiddlewareGroups();
        $aliases = $this->router->getMiddleware();

        foreach ($names as $name) {
            $resolved[$name] = true;
            $base = explode(':', $name, 2)[0];

            if (isset($groups[$base]) && ! isset($visitedGroups[$base])) {
                $this->expand($this->names((array) $groups[$base]), $resolved, [...$visitedGroups, $base => true]);

                continue;
            }

            if (isset($aliases[$base]) && is_string($aliases[$base])) {
                $resolved[ltrim($aliases[$base], '\\')] = true;
            }

            foreach ($aliases as $alias => $class) {
                if (is_string($class) && ltrim($class, '\\') === ltrim($base, '\\')) {
                    $resolved[(string) $alias] = true;
                }
            }
        }
    }

    /**
     * Middleware declared through the framework's controller attributes,
     * honouring their only and except options. Only framework attributes are
     * instantiated; application subclasses are ignored.
     *
     * @return list<string>
     */
    private function attributes(?string $controllerClass, ?string $controllerMethod, string $attribute): array
    {
        if (
            $controllerClass === null
            || $controllerMethod === null
            || ! class_exists($attribute)
            || ! class_exists($controllerClass)
        ) {
            return [];
        }

        try {
            $class = new ReflectionClass($controllerClass);
            $reflections = [];

            for ($current = $class; $current !== false; $current = $current->getParentClass()) {
                $reflections = [...$current->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF), ...$reflections];
            }

            if ($class->hasMethod($controllerMethod)) {
                $reflections = [
                    ...$reflections,
                    ...$class->getMethod($controllerMethod)->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF),
                ];
            }
        } catch (Throwable) {
            return [];
        }

        $middleware = [];

        foreach ($reflections as $reflection) {
            if (! str_starts_with($reflection->getName(), 'Illuminate\\')) {
                continue;
            }

            try {
                $instance = $reflection->newInstance();
            } catch (Throwable) {
                continue;
            }

            $only = $instance->only ?? null;
            $except = $instance->except ?? null;

            if (
                (is_array($only) && ! in_array($controllerMethod, $only, true))
                || (is_array($except) && in_array($controllerMethod, $except, true))
            ) {
                continue;
            }

            $middleware = [...$middleware, ...$this->names([$instance->middleware ?? null])];
        }

        return $middleware;
    }

    /**
     * Middleware may be declared as strings, closures or objects; the graph
     * only keeps serializable names.
     *
     * @param  array<mixed>  $middleware
     * @return list<string>
     */
    private function names(array $middleware): array
    {
        $names = [];

        foreach ($middleware as $entry) {
            $name = match (true) {
                is_string($entry) => $entry,
                $entry instanceof Closure => 'Closure',
                is_object($entry) => $entry::class,
                default => null,
            };

            if ($name !== null && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
