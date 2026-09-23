<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerActionData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ControllerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchableData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\EventData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\FormRequestData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ListenerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\PolicyData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\RouteData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Domain\Enums\NodeType;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;

function sampleHttpMap(): HttpMapData
{
    $dispatch = new DispatchReference(
        class: 'App\Mail\OrderShipped',
        kind: DispatchKind::Mailable,
        via: ['App\Actions\ShipOrder'],
        location: 'app/Actions/ShipOrder.php:21',
    );

    return new HttpMapData(
        routes: [new RouteData(
            id: StableIdentifier::route(['PUT'], null, 'orders/{order}'),
            methods: ['PUT'],
            uri: 'orders/{order}',
            name: 'orders.update',
            domain: null,
            actionType: RouteData::ACTION_CONTROLLER,
            controllerClass: 'App\Http\Controllers\OrderController',
            controllerMethod: 'update',
            livewireClass: null,
            view: null,
            middleware: ['web', 'auth'],
            boundParameters: ['order' => 'App\Models\Order'],
            file: null,
            status: DiscoveryStatus::Complete,
            warnings: [],
        )],
        controllers: [new ControllerData(
            id: StableIdentifier::controller('App\Http\Controllers\OrderController'),
            class: 'App\Http\Controllers\OrderController',
            file: 'app/Http/Controllers/OrderController.php',
            actions: ['update' => new ControllerActionData(
                formRequests: ['App\Http\Requests\UpdateOrderRequest'],
                models: ['App\Models\Order' => ['binding'], 'App\Models\Product' => ['static']],
                dispatches: [$dispatch],
            )],
            status: DiscoveryStatus::Complete,
            warnings: [],
        )],
        formRequests: [new FormRequestData(
            id: StableIdentifier::formRequest('App\Http\Requests\UpdateOrderRequest'),
            class: 'App\Http\Requests\UpdateOrderRequest',
            file: null,
            hasAuthorize: true,
            hasRules: true,
            rules: ['status' => ['required', 'string']],
            status: DiscoveryStatus::Complete,
            warnings: [],
        )],
        policies: [new PolicyData(
            id: StableIdentifier::policy('App\Policies\OrderPolicy'),
            class: 'App\Policies\OrderPolicy',
            file: null,
            modelClass: 'App\Models\Order',
            abilities: ['update', 'view'],
            source: PolicyData::SOURCE_CONVENTION,
            status: DiscoveryStatus::Complete,
            warnings: [],
        )],
        events: [new EventData(
            id: StableIdentifier::event('App\Events\OrderPlaced'),
            class: 'App\Events\OrderPlaced',
            file: null,
            listenerClasses: ['App\Listeners\SendReceipt'],
            closureListenerCount: 1,
            status: DiscoveryStatus::Complete,
            warnings: [],
        )],
        listeners: [new ListenerData(
            id: StableIdentifier::listener('App\Listeners\SendReceipt'),
            class: 'App\Listeners\SendReceipt',
            file: null,
            events: ['App\Events\OrderPlaced' => 'handle'],
            queued: true,
            dispatches: [$dispatch],
            status: DiscoveryStatus::Partial,
            warnings: ['Something could not be read.'],
        )],
        dispatchables: [new DispatchableData(
            id: $dispatch->targetId(),
            kind: DispatchKind::Mailable,
            class: 'App\Mail\OrderShipped',
            file: null,
            queued: false,
            dispatches: [],
            status: DiscoveryStatus::Complete,
            warnings: [],
        )],
    );
}

it('round trips every HTTP DTO through its array form', function (): void {
    $map = sampleHttpMap();

    expect(HttpMapData::fromArray($map->toArray())->toArray())->toBe($map->toArray());
});

it('lists the model classes referenced by bindings and controller actions', function (): void {
    expect(sampleHttpMap()->referencedModelClasses())->toBe(['App\Models\Order', 'App\Models\Product']);
});

it('keeps the HTTP map when a snapshot is serialized', function (): void {
    $snapshot = new ApplicationSnapshot(
        fingerprint: 'abc',
        generatedAt: new DateTimeImmutable('2026-09-23T10:00:00+00:00'),
        models: [],
        relations: [],
        resources: [],
        panels: [],
        warnings: [],
        http: sampleHttpMap(),
    );

    expect(ApplicationSnapshot::fromArray($snapshot->toArray())->toArray())->toBe($snapshot->toArray());
});

it('reads snapshots serialized before the HTTP map existed', function (): void {
    $restored = ApplicationSnapshot::fromArray([
        'fingerprint' => 'abc',
        'generated_at' => '2026-09-23T10:00:00+00:00',
        'models' => [],
        'relations' => [],
        'resources' => [],
        'panels' => [],
        'warnings' => [],
    ]);

    expect($restored->http->routes)->toBe([])
        ->and($restored->http->toArray()['controllers'])->toBe([]);
});

it('builds readable and stable identifiers for HTTP nodes', function (): void {
    expect(StableIdentifier::route(['post', 'GET'], null, '/orders'))->toBe('route:GET|POST:/orders')
        ->and(StableIdentifier::route(['GET'], 'api.example.com', 'orders/{order}'))->toBe('route:GET:api.example.com/orders/{order}')
        ->and(StableIdentifier::controller('App\Http\Controllers\OrderController'))->toBe('controller:app.http.controllers.order-controller')
        ->and(StableIdentifier::formRequest('App\Http\Requests\StoreOrder'))->toBe('form-request:app.http.requests.store-order')
        ->and(StableIdentifier::policy('App\Policies\OrderPolicy'))->toBe('policy:app.policies.order-policy')
        ->and(StableIdentifier::listener('App\Listeners\SendReceipt'))->toBe('listener:app.listeners.send-receipt')
        ->and(StableIdentifier::dispatchTarget(DispatchKind::Event, 'App\Events\OrderPlaced'))->toBe('event:app.events.order-placed')
        ->and(StableIdentifier::dispatchTarget(DispatchKind::Job, 'App\Jobs\ShipOrder'))->toBe('job:app.jobs.ship-order')
        ->and(StableIdentifier::dispatchTarget(DispatchKind::Notification, 'App\Notifications\Shipped'))->toBe('notification:app.notifications.shipped');
});

it('maps dispatch kinds to node types and flags HTTP node types', function (): void {
    expect(DispatchKind::Mailable->nodeType())->toBe(NodeType::Mailable)
        ->and(NodeType::Route->isHttp())->toBeTrue()
        ->and(NodeType::Model->isHttp())->toBeFalse()
        ->and(RouteData::fromArray(sampleHttpMap()->routes[0]->toArray())->label())->toBe('PUT /orders/{order}');
});
