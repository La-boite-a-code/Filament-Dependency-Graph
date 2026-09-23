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
     * The router resolves the effective middleware exactly as it would for
     * a request (aliases, groups, exclusions and their subclasses), without
     * instantiating anything. The declared list keeps what the developer
     * wrote and still applies; the resolved list adds, for filtering, every
     * alias and group name that leads to an effective middleware.
     *
     * @return array{declared: list<string>, resolved: list<string>}
     */
    public function resolve(Route $route, ?string $controllerClass, ?string $controllerMethod): array
    {
        $declaredRaw = [
            ...(array) $route->middleware(),
            ...$this->attributes($controllerClass, $controllerMethod, self::MIDDLEWARE_ATTRIBUTE),
        ];

        $excludedRaw = [
            ...(array) $route->getAction('excluded_middleware'),
            ...$this->attributes($controllerClass, $controllerMethod, self::WITHOUT_MIDDLEWARE_ATTRIBUTE),
        ];

        $effective = $this->resolveNames($declaredRaw, $excludedRaw) ?? $this->names($declaredRaw);
        $effectiveBases = array_fill_keys(array_map($this->base(...), $effective), true);

        $candidates = [];
        $this->expand($this->names($declaredRaw), $candidates, []);

        $resolved = array_fill_keys($effective, true);

        foreach ($effective as $name) {
            foreach ($this->aliasesOf($this->base($name)) as $alias) {
                $parameters = explode(':', $name, 2)[1] ?? null;
                $resolved[$parameters === null ? $alias : $alias . ':' . $parameters] = true;
            }
        }

        $declared = [];

        foreach (array_keys($candidates) as $candidate) {
            if (! $this->leadsTo($candidate, $effectiveBases)) {
                continue;
            }

            $resolved[$candidate] = true;

            if (in_array($candidate, $this->names($declaredRaw), true)) {
                $declared[] = $candidate;
            }
        }

        return [
            'declared' => array_values(array_unique($declared)),
            'resolved' => array_keys($resolved),
        ];
    }

    /**
     * @param  array<mixed>  $middleware
     * @param  array<mixed>  $excluded
     * @return list<string>|null Null when the router cannot resolve them.
     */
    private function resolveNames(array $middleware, array $excluded = []): ?array
    {
        try {
            return $this->names($this->router->resolveMiddleware($middleware, $excluded));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a name still produces at least one effective middleware.
     * Names the router cannot resolve are kept as written.
     *
     * @param  array<string, true>  $effectiveBases
     */
    private function leadsTo(string $name, array $effectiveBases): bool
    {
        $own = $this->resolveNames([$name]);

        if ($own === null || $own === []) {
            return true;
        }

        foreach ($own as $entry) {
            if (isset($effectiveBases[$this->base($entry)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Group members and group names reachable from the declared names.
     *
     * @param  list<string>  $names
     * @param  array<string, true>  $candidates
     * @param  array<string, true>  $visitedGroups
     */
    private function expand(array $names, array &$candidates, array $visitedGroups): void
    {
        $groups = $this->router->getMiddlewareGroups();

        foreach ($names as $name) {
            $candidates[$name] = true;
            $base = $this->base($name);

            if (isset($groups[$base]) && ! isset($visitedGroups[$base])) {
                $this->expand($this->names((array) $groups[$base]), $candidates, [...$visitedGroups, $base => true]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function aliasesOf(string $class): array
    {
        $aliases = [];

        foreach ($this->router->getMiddleware() as $alias => $target) {
            if (is_string($target) && ltrim($target, '\\') === ltrim($class, '\\')) {
                $aliases[] = (string) $alias;
            }
        }

        return $aliases;
    }

    private function base(string $name): string
    {
        return ltrim(explode(':', $name, 2)[0], '\\');
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
