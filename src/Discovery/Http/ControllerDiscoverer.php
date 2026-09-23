<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerActionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

/**
 * Inspects the controllers referenced by routes, one routed method at a
 * time. Controllers are never instantiated and actions never called.
 */
final class ControllerDiscoverer
{
    public function __construct(
        private readonly MethodInspector $inspector,
    ) {}

    /**
     * @param  list<RouteData>  $routes
     * @param  array<string, true>  $eventClasses
     * @return list<ControllerData>
     */
    public function discover(array $routes, DiscoveryContext $context, array $eventClasses): array
    {
        /** @var array<string, array<string, array<string, string>>> $actions Controller to method to bound parameters. */
        $actions = [];

        foreach ($routes as $route) {
            if ($route->controllerClass === null || $route->controllerMethod === null) {
                continue;
            }

            $bound = $actions[$route->controllerClass][$route->controllerMethod] ?? [];
            $actions[$route->controllerClass][$route->controllerMethod] = [...$bound, ...$route->boundParameters];
        }

        $controllers = [];

        foreach ($actions as $class => $methods) {
            $controller = $this->discoverController($class, $methods, $context, $eventClasses);
            $controllers[$controller->id] = $controller;
        }

        ksort($controllers, SORT_STRING);

        return array_values($controllers);
    }

    /**
     * @param  array<string, array<string, string>>  $methods  Method to bound parameters.
     * @param  array<string, true>  $eventClasses
     */
    private function discoverController(string $class, array $methods, DiscoveryContext $context, array $eventClasses): ControllerData
    {
        $warnings = [];
        $file = null;
        $actions = [];

        try {
            if (! class_exists($class)) {
                throw new RuntimeException('class not found');
            }

            $reflection = new ReflectionClass($class);
            $path = $reflection->getFileName();
            $file = is_string($path) ? PackagePath::relative($path, $context->basePath) : null;
        } catch (Throwable $exception) {
            return new ControllerData(
                id: StableIdentifier::controller($class),
                class: $class,
                file: null,
                actions: [],
                status: DiscoveryStatus::Failed,
                warnings: [sprintf('Controller could not be loaded: %s', $exception->getMessage())],
            );
        }

        ksort($methods, SORT_STRING);

        foreach ($methods as $method => $bound) {
            if (! $reflection->hasMethod($method)) {
                $warnings[] = sprintf('Routed method [%s] does not exist.', $method);

                continue;
            }

            $actions[$method] = $this->discoverAction($reflection->getMethod($method), $bound, $context, $eventClasses, $warnings);
        }

        return new ControllerData(
            id: StableIdentifier::controller($class),
            class: $class,
            file: $file,
            actions: $actions,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * @param  array<string, string>  $bound
     * @param  array<string, true>  $eventClasses
     * @param  list<string>  $warnings
     */
    private function discoverAction(
        ReflectionMethod $method,
        array $bound,
        DiscoveryContext $context,
        array $eventClasses,
        array &$warnings,
    ): ControllerActionData {
        $formRequests = [];
        $models = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if ($this->isSubclassOf($class, FormRequest::class)) {
                $formRequests[] = $class;
            } elseif ($this->isSubclassOf($class, Model::class)) {
                $boundClass = $bound[$parameter->getName()] ?? $bound[Str::snake($parameter->getName())] ?? null;
                $models[$class][] = $boundClass === $class
                    ? ControllerActionData::SOURCE_BINDING
                    : ControllerActionData::SOURCE_TYPE;
            }
        }

        $findings = $this->inspector->inspect($method, $context, $eventClasses);

        if (! $findings->readable) {
            $warnings[] = sprintf('Source of [%s] could not be read; static references were not inspected.', $method->getName());
        }

        foreach ($findings->models as $class) {
            $models[$class][] = ControllerActionData::SOURCE_STATIC;
        }

        foreach ($models as &$sources) {
            $sources = array_values(array_unique($sources));
            sort($sources, SORT_STRING);
        }

        unset($sources);
        ksort($models, SORT_STRING);

        $formRequests = array_values(array_unique($formRequests));
        sort($formRequests, SORT_STRING);

        return new ControllerActionData(
            formRequests: $formRequests,
            models: $models,
            dispatches: $findings->dispatches,
            views: $findings->views,
        );
    }

    private function isSubclassOf(string $class, string $parent): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, $parent);
        } catch (Throwable) {
            return false;
        }
    }
}
