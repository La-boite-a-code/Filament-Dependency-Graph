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
    ) {}

    /**
     * @return list<RouteData>
     */
    public function discover(DiscoveryContext $context): array
    {
        $routes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
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
            // Closures restored from the route cache are serialized strings.
            $actionType = RouteData::ACTION_CLOSURE;
        }

        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $methods = $methods === [] ? ['HEAD'] : $methods;

        $view = $route->defaults['view'] ?? null;

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
            middleware: $this->middleware($route),
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
     * Declared middleware, excluded middleware removed. Middleware that a
     * controller declares itself is not read, because reading it may
     * require instantiating the controller.
     *
     * @return list<string>
     */
    private function middleware(Route $route): array
    {
        $excluded = array_map(
            static fn (mixed $middleware): string => is_string($middleware) ? $middleware : 'Closure',
            $route->excludedMiddleware(),
        );

        $middleware = [];

        foreach ($route->middleware() as $name) {
            if (! in_array($name, $excluded, true) && ! in_array($name, $middleware, true)) {
                $middleware[] = $name;
            }
        }

        return $middleware;
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
            if (! $parameter instanceof ReflectionParameter || ! in_array($parameter->getName(), $names, true)) {
                continue;
            }

            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = ClassName::normalize($type->getName());

            try {
                if (is_subclass_of($class, Model::class)) {
                    $bound[$parameter->getName()] = $class;
                }
            } catch (Throwable) {
                continue;
            }
        }

        ksort($bound, SORT_STRING);

        return $bound;
    }
}
