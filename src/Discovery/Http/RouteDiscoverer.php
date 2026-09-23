<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Str;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use Livewire\Component;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Reads the application routes from the router. Nothing is dispatched and
 * no controller is instantiated: middleware comes from the route
 * definition, bound models from the action signature.
 */
final class RouteDiscoverer implements CollectsDiscoveryWarnings
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly Router $router,
        private readonly MiddlewareResolver $middleware,
    ) {}

    /**
     * @return list<RouteData>
     */
    public function discover(DiscoveryContext $context): array
    {
        $routes = [];
        $cachedClosures = 0;

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! $context->includeVendorRoutes && $this->isCachedClosure($route)) {
                $cachedClosures += $this->isExcluded($route, $context) ? 0 : 1;

                continue;
            }

            try {
                $data = $this->discoverRoute($route, $context);
            } catch (Throwable $exception) {
                $this->warnings[] = new DiscoveryWarning(
                    type: 'route_not_readable',
                    message: sprintf('Route [%s] could not be read: %s', $route->uri(), $exception->getMessage()),
                    exceptionClass: $exception::class,
                );

                continue;
            }

            if ($data !== null) {
                $routes[$data->id] = $data;
            }
        }

        if ($cachedClosures > 0) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'route_cache_closures_skipped',
                message: sprintf(
                    '%d closure route(s) restored from the route cache were skipped: their file, hence whether they belong to the application or to the framework (such as the /up health route), is unknown. Clear the route cache or enable http.include_vendor_routes to map them.',
                    $cachedClosures,
                ),
            );
        }

        $routes = array_values($routes);

        usort($routes, static fn (RouteData $a, RouteData $b): int => [$a->uri, $a->id] <=> [$b->uri, $b->id]);

        return $routes;
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    private function discoverRoute(Route $route, DiscoveryContext $context): ?RouteData
    {
        if ($this->isExcluded($route, $context)) {
            return null;
        }

        $uses = $route->getAction('uses');
        $controllerClass = null;
        $controllerMethod = null;
        $livewireClass = null;
        $file = null;
        $warnings = [];

        if ($uses instanceof Closure) {
            $actionType = RouteData::ACTION_CLOSURE;
            $path = (new ReflectionFunction($uses))->getFileName();

            if (is_string($path)) {
                if (! $context->includeVendorRoutes && ! $this->isApplicationFile($path, $context)) {
                    return null;
                }

                $file = PackagePath::relative($path, $context->basePath);
            }
        } elseif (is_string($uses) && $route->getControllerClass() !== null) {
            $class = ltrim((string) $route->getControllerClass(), '\\');
            $method = Str::parseCallback($uses, '__invoke')[1] ?? '__invoke';

            [$actionType, $controllerClass, $controllerMethod, $livewireClass] = match (true) {
                $class === ViewController::class => [RouteData::ACTION_VIEW, null, null, null],
                $class === RedirectController::class => [RouteData::ACTION_REDIRECT, null, null, null],
                $this->isLivewireComponent($class) => [RouteData::ACTION_LIVEWIRE, null, null, $class],
                default => [RouteData::ACTION_CONTROLLER, $class, $method, null],
            };

            if (! $context->includeVendorRoutes && ! $this->isApplicationAction($actionType, $class, $context)) {
                return null;
            }
        } else {
            // Closures restored from the route cache are serialized strings;
            // they only get here when vendor routes are included.
            $actionType = RouteData::ACTION_CLOSURE;
        }

        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $methods = $methods === [] ? ['HEAD'] : $methods;

        $view = $route->defaults['view'] ?? null;
        // Full-page Livewire components are invokable controllers too.
        $middleware = $this->middleware->resolve(
            $route,
            $controllerClass ?? $livewireClass,
            $controllerMethod ?? ($livewireClass === null ? null : '__invoke'),
        );

        return new RouteData(
            id: StableIdentifier::route($methods, $route->getDomain(), $route->uri()),
            methods: $methods,
            uri: $route->uri(),
            name: $route->getName(),
            domain: $route->getDomain(),
            actionType: $actionType,
            controllerClass: $controllerClass,
            controllerMethod: $controllerMethod,
            livewireClass: $livewireClass,
            view: is_string($view) ? $view : null,
            middleware: $middleware['declared'],
            resolvedMiddleware: $middleware['resolved'],
            boundParameters: $this->boundParameters($route, $warnings),
            file: $file,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    private function isExcluded(Route $route, DiscoveryContext $context): bool
    {
        $name = $route->getName();

        if ($name !== null && $context->excludedRouteNames !== [] && Str::is($context->excludedRouteNames, $name)) {
            return true;
        }

        return $context->excludedRouteUris !== [] && Str::is($context->excludedRouteUris, $route->uri());
    }

    private function isCachedClosure(Route $route): bool
    {
        $uses = $route->getAction('uses');

        return is_string($uses) && str_starts_with($uses, 'O:') && str_contains($uses, 'SerializableClosure');
    }

    private function isApplicationAction(string $actionType, string $class, DiscoveryContext $context): bool
    {
        return match ($actionType) {
            RouteData::ACTION_CONTROLLER => NamespaceMatcher::matchesNamespace($class, $context->httpControllerNamespaces),
            RouteData::ACTION_LIVEWIRE => NamespaceMatcher::matchesNamespace($class, $context->livewireNamespaces),
            default => true,
        };
    }

    private function isApplicationFile(string $path, DiscoveryContext $context): bool
    {
        if ($context->basePath === '') {
            return true;
        }

        return PackagePath::isApplicationOwned($path, $context->basePath, $context->vendorPath);
    }

    private function isLivewireComponent(string $class): bool
    {
        try {
            return class_exists(Component::class) && is_subclass_of($class, Component::class);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Route parameters bound to an Eloquent model through the action
     * signature (implicit route model binding).
     *
     * @param  list<string>  $warnings
     * @return array<string, string>
     */
    private function boundParameters(Route $route, array &$warnings): array
    {
        try {
            $parameters = $route->signatureParameters(['subClass' => UrlRoutable::class]);
        } catch (Throwable $exception) {
            $warnings[] = sprintf('The action signature could not be read: %s', $exception->getMessage());

            return [];
        }

        $names = $route->parameterNames();
        $bound = [];

        foreach ($parameters as $parameter) {
            if (! $parameter instanceof ReflectionParameter) {
                continue;
            }

            // Implicit binding also matches $orderItem to {order_item}.
            $name = match (true) {
                in_array($parameter->getName(), $names, true) => $parameter->getName(),
                in_array(Str::snake($parameter->getName()), $names, true) => Str::snake($parameter->getName()),
                default => null,
            };

            if ($name === null) {
                continue;
            }

            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = ClassName::normalize($type->getName());

            try {
                if (is_subclass_of($class, Model::class)) {
                    $bound[$name] = $class;
                }
            } catch (Throwable) {
                continue;
            }
        }

        ksort($bound, SORT_STRING);

        return $bound;
    }
}
