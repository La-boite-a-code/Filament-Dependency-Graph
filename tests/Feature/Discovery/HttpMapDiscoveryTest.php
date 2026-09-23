<?php

declare(strict_types=1);

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use LaBoiteACode\DependencyGraph\Application\BuildDependencyGraph;
use LaBoiteACode\DependencyGraph\Contracts\ApplicationDiscovery;
use LaBoiteACode\DependencyGraph\Contracts\PolicyDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchableData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\EventData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\ListenerData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\PolicyData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Broken\BrokenListener;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\NotificationController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderArchived;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ArchiveOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\NotifyWarehouse;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ShipOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners\SendOrderConfirmation;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners\UpdateInventory;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Mail\OrderShippedMail;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Notifications\OrderUpdated;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Policies\CatalogPolicy;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Requests\StoreOrderRequest;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Policies\OrderPolicy;

function httpFixtureSnapshot(mixed ...$overrides): ApplicationSnapshot
{
    return app(ApplicationDiscovery::class)->discover(test()->fixtureContext(...$overrides));
}

it('maps the fixture application end to end', function (): void {
    $http = httpFixtureSnapshot()->http;

    expect($http->routes)->toHaveCount(11)
        ->and($http->controllers)->toHaveCount(2)
        ->and($http->formRequests)->toHaveCount(2)
        ->and($http->policies)->toHaveCount(2)
        ->and($http->events)->toHaveCount(2)
        ->and($http->listeners)->toHaveCount(2)
        ->and($http->dispatchables)->toHaveCount(5);
});

it('reads events and application listeners from the dispatcher', function (): void {
    $http = httpFixtureSnapshot()->http;

    expect(array_map(static fn (EventData $event): array => [
        $event->class,
        $event->listenerClasses,
        $event->closureListenerCount,
    ], $http->events))->toBe([
        [OrderArchived::class, [UpdateInventory::class], 1],
        [OrderPlaced::class, [SendOrderConfirmation::class, UpdateInventory::class], 0],
    ]);

    expect(array_map(static fn (ListenerData $listener): array => [
        $listener->class,
        $listener->events,
        $listener->queued,
        array_map(static fn ($dispatch): string => $dispatch->class, $listener->dispatches),
    ], $http->listeners))->toBe([
        [SendOrderConfirmation::class, [OrderPlaced::class => 'handle'], false, [OrderShippedMail::class]],
        [UpdateInventory::class, [OrderArchived::class => 'release', OrderPlaced::class => 'handle'], true, []],
    ]);
});

it('collects jobs, mails and notifications, following jobs that dispatch jobs', function (): void {
    $dispatchables = httpFixtureSnapshot()->http->dispatchables;

    expect(array_map(static fn (DispatchableData $item): array => [
        $item->kind,
        $item->class,
        $item->queued,
    ], $dispatchables))->toBe([
        [DispatchKind::Job, ArchiveOrder::class, true],
        [DispatchKind::Job, NotifyWarehouse::class, false],
        [DispatchKind::Job, ShipOrder::class, true],
        [DispatchKind::Mailable, OrderShippedMail::class, false],
        [DispatchKind::Notification, OrderUpdated::class, true],
    ]);

    expect(array_map(static fn ($dispatch): string => $dispatch->class, $dispatchables[2]->dispatches))
        ->toBe([NotifyWarehouse::class]);
});

it('resolves registered and conventional policies without instantiating them', function (): void {
    $policies = httpFixtureSnapshot()->http->policies;
    $byModel = [];

    foreach ($policies as $policy) {
        $byModel[$policy->modelClass] = $policy;
    }

    expect($byModel[Order::class]->class)->toBe(OrderPolicy::class)
        ->and($byModel[Order::class]->source)->toBe(PolicyData::SOURCE_CONVENTION)
        ->and($byModel[Order::class]->abilities)->toBe(['update', 'view'])
        ->and($byModel[Product::class]->class)->toBe(CatalogPolicy::class)
        ->and($byModel[Product::class]->source)->toBe(PolicyData::SOURCE_REGISTERED)
        ->and($byModel[Product::class]->abilities)->toBe(['viewAny']);
});

it('honours a custom policy name guesser', function (): void {
    Gate::guessPolicyNamesUsing(static fn (string $class): string => CatalogPolicy::class);

    $policies = app(PolicyDiscoverer::class)->discover([Order::class], $this->fixtureContext());

    expect($policies[0]->class)->toBe(CatalogPolicy::class)
        ->and($policies[0]->source)->toBe(PolicyData::SOURCE_CONVENTION);
});

it('skips the HTTP map entirely when disabled', function (): void {
    $http = httpFixtureSnapshot(discoverHttp: false)->http;

    expect($http->toArray())->toBe([
        'routes' => [],
        'controllers' => [],
        'form_requests' => [],
        'policies' => [],
        'events' => [],
        'listeners' => [],
        'dispatchables' => [],
    ]);
});

it('round trips the discovered HTTP map through the snapshot cache format', function (): void {
    $snapshot = httpFixtureSnapshot();

    expect(ApplicationSnapshot::fromArray($snapshot->toArray())->toArray())->toBe($snapshot->toArray());
});

it('changes the fingerprint when only the HTTP map changes', function (): void {
    $before = httpFixtureSnapshot()->fingerprint;

    Route::get('reports', [OrderController::class, 'index'])->name('reports');

    expect(httpFixtureSnapshot()->fingerprint)->not->toBe($before);
});

it('never instantiates controllers, policies or form requests', function (): void {
    OrderController::$instances = 0;
    OrderPolicy::$instances = 0;
    StoreOrderRequest::$instances = 0;

    httpFixtureSnapshot();

    expect(OrderController::$instances)->toBe(0)
        ->and(OrderPolicy::$instances)->toBe(0)
        ->and(StoreOrderRequest::$instances)->toBe(0);

    httpFixtureSnapshot(invokeFormRequestRules: true);

    expect(OrderController::$instances)->toBe(0)
        ->and(OrderPolicy::$instances)->toBe(0)
        ->and(StoreOrderRequest::$instances)->toBe(1);
});

it('leaves the Filament and Laravel scopes untouched', function (GraphScope $scope): void {
    $build = static fn (bool $http): array => app(BuildDependencyGraph::class)->execute(
        httpFixtureSnapshot(discoverHttp: $http),
        new GraphQuery(scope: $scope),
    )->toArray();

    expect($build(true))->toBe($build(false));
})->with([GraphScope::Filament, GraphScope::Laravel]);

it('only adds vendor models referenced by controllers when vendor models are enabled', function (): void {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');

    $classes = static fn (ApplicationSnapshot $snapshot): array => array_map(
        static fn ($model): string => $model->class,
        $snapshot->models,
    );

    expect($classes(httpFixtureSnapshot()))->not->toContain(DatabaseNotification::class)
        ->and($classes(httpFixtureSnapshot(vendorModelsEnabled: true)))->toContain(DatabaseNotification::class);
});

it('shows one node for a policy guarding several models', function (): void {
    Gate::policy(Customer::class, CatalogPolicy::class);

    $graph = app(BuildDependencyGraph::class)->execute(httpFixtureSnapshot(), new GraphQuery(scope: GraphScope::Http));
    $policy = $graph->node(StableIdentifier::policy(CatalogPolicy::class));

    expect($policy->metadata['model_classes'])->toBe([Customer::class, Product::class])
        ->and($policy->subtitle)->toBe('Customer, Product')
        ->and(count($graph->incomingEdges($policy->id)))->toBe(2);
});

it('isolates classes that cannot be loaded', function (): void {
    Event::listen(OrderPlaced::class, BrokenListener::class);
    Gate::guessPolicyNamesUsing(static fn (): string => BrokenListener::class);

    $snapshot = httpFixtureSnapshot(httpApplicationNamespaces: ['LaBoiteACode\\DependencyGraph\\Tests\\Fixtures\\Http\\']);

    expect($snapshot->http->routes)->not->toBe([])
        ->and($snapshot->http->listeners)->not->toBe([])
        ->and(collect($snapshot->warnings)->pluck('type'))->toContain('policy_not_resolvable');
});
