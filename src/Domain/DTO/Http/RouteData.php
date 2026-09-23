<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\DTO\Http;

use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;

final readonly class RouteData
{
    public const ACTION_CONTROLLER = 'controller';

    public const ACTION_CLOSURE = 'closure';

    public const ACTION_LIVEWIRE = 'livewire';

    public const ACTION_VIEW = 'view';

    public const ACTION_REDIRECT = 'redirect';

    /**
     * @param  list<string>  $methods  HTTP methods, HEAD excluded.
     * @param  list<string>  $middleware  Middleware as declared on the route and the controller attributes.
     * @param  list<string>  $resolvedMiddleware  Declared middleware plus group members, aliases and their classes.
     * @param  array<string, string>  $boundParameters  Route parameter to bound model class.
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $id,
        public array $methods,
        public string $uri,
        public ?string $name,
        public ?string $domain,
        public string $actionType,
        public ?string $controllerClass,
        public ?string $controllerMethod,
        public ?string $livewireClass,
        public ?string $view,
        public array $middleware,
        public array $resolvedMiddleware,
        public array $boundParameters,
        public ?string $file,
        public DiscoveryStatus $status,
        public array $warnings,
        public ?string $livewireComponent = null,
    ) {}

    public function label(): string
    {
        $methods = array_diff(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $this->methods) === []
            ? 'ANY'
            : implode('|', $this->methods);

        return $methods . ' /' . ltrim($this->uri, '/');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: string, methods: list<string>, uri: string, name: string|null, domain: string|null, action_type: string, controller_class: string|null, controller_method: string|null, livewire_class: string|null, view: string|null, middleware: list<string>, resolved_middleware: list<string>, bound_parameters: array<string, string>, file: string|null, status: string, warnings: list<string>, livewire_component?: string|null} $data */
        return new self(
            id: $data['id'],
            methods: $data['methods'],
            uri: $data['uri'],
            name: $data['name'],
            domain: $data['domain'],
            actionType: $data['action_type'],
            controllerClass: $data['controller_class'],
            controllerMethod: $data['controller_method'],
            livewireClass: $data['livewire_class'],
            view: $data['view'],
            middleware: $data['middleware'],
            resolvedMiddleware: $data['resolved_middleware'],
            boundParameters: $data['bound_parameters'],
            file: $data['file'],
            status: DiscoveryStatus::from($data['status']),
            warnings: $data['warnings'],
            livewireComponent: $data['livewire_component'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'methods' => $this->methods,
            'uri' => $this->uri,
            'name' => $this->name,
            'domain' => $this->domain,
            'action_type' => $this->actionType,
            'controller_class' => $this->controllerClass,
            'controller_method' => $this->controllerMethod,
            'livewire_class' => $this->livewireClass,
            'view' => $this->view,
            'middleware' => $this->middleware,
            'resolved_middleware' => $this->resolvedMiddleware,
            'bound_parameters' => $this->boundParameters,
            'file' => $this->file,
            'status' => $this->status->value,
            'warnings' => $this->warnings,
            'livewire_component' => $this->livewireComponent,
        ];
    }
}
