<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Discovery\Http\DispatchClassifier;
use LaBoiteACode\DependencyGraph\Discovery\Http\MethodInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Actions\PlaceOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers\OrderController;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ArchiveOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ShipOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners\UpdateInventory;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Mail\OrderShippedMail;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Notifications\OrderUpdated;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Repositories\OrderRepository;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

/**
 * @return list<string>
 */
function describeDispatches(array $dispatches): array
{
    return array_map(
        static fn (DispatchReference $dispatch): string => sprintf(
            '%s %s%s',
            $dispatch->kind->value,
            class_basename($dispatch->class),
            $dispatch->via === [] ? '' : ' via ' . implode(',', array_map('class_basename', $dispatch->via)),
        ),
        $dispatches,
    );
}

it('classifies dispatchable classes from their type hierarchy', function (): void {
    $classifier = new DispatchClassifier;

    expect($classifier->classify(ShipOrder::class))->toBe(DispatchKind::Job)
        ->and($classifier->classify(OrderPlaced::class))->toBe(DispatchKind::Event)
        ->and($classifier->classify(OrderShippedMail::class))->toBe(DispatchKind::Mailable)
        ->and($classifier->classify(OrderUpdated::class))->toBe(DispatchKind::Notification)
        ->and($classifier->classify(Order::class))->toBeNull()
        ->and($classifier->classify('App\Missing\Thing'))->toBeNull()
        ->and($classifier->classify(Order::class, [Order::class => true]))->toBe(DispatchKind::Event)
        ->and($classifier->isQueued(ShipOrder::class))->toBeTrue()
        ->and($classifier->isQueued(UpdateInventory::class))->toBeTrue()
        ->and($classifier->isQueued(OrderShippedMail::class))->toBeFalse();
});

it('finds mails and notifications created in a controller action', function (): void {
    $findings = app(MethodInspector::class)->inspect(
        new ReflectionMethod(OrderController::class, 'update'),
        $this->fixtureContext(),
        [],
    );

    expect($findings->readable)->toBeTrue()
        ->and($findings->models)->toBe([Customer::class])
        ->and(describeDispatches($findings->dispatches))->toBe([
            'mailable OrderShippedMail',
            'notification OrderUpdated',
        ])
        ->and($findings->dispatches[0]->location)->toBe('tests/Fixtures/Http/Controllers/OrderController.php:50');
});

it('follows injected action classes one level deep', function (): void {
    $findings = app(MethodInspector::class)->inspect(
        new ReflectionMethod(OrderController::class, 'store'),
        $this->fixtureContext(),
        [],
    );

    expect($findings->models)->toBe([Order::class])
        ->and(describeDispatches($findings->dispatches))->toBe([
            'event OrderPlaced via PlaceOrder',
            'job ShipOrder via PlaceOrder',
        ])
        ->and($findings->dispatches[0]->via)->toBe([PlaceOrder::class]);
});

it('follows promoted constructor properties and only the called methods', function (): void {
    $findings = app(MethodInspector::class)->inspect(
        new ReflectionMethod(OrderController::class, 'destroy'),
        $this->fixtureContext(),
        [],
    );

    expect(describeDispatches($findings->dispatches))->toBe(['job ArchiveOrder via OrderRepository'])
        ->and($findings->dispatches[0]->via)->toBe([OrderRepository::class])
        ->and($findings->dispatches[0]->class)->toBe(ArchiveOrder::class);
});

it('does not follow collaborators when disabled', function (): void {
    $findings = app(MethodInspector::class)->inspect(
        new ReflectionMethod(OrderController::class, 'store'),
        $this->fixtureContext(followInjectedClasses: false),
        [],
    );

    expect($findings->dispatches)->toBe([])
        ->and($findings->models)->toBe([]);
});

it('does not follow classes outside the application namespaces', function (): void {
    $findings = app(MethodInspector::class)->inspect(
        new ReflectionMethod(OrderController::class, 'store'),
        $this->fixtureContext(httpApplicationNamespaces: ['App\\']),
        [],
    );

    expect($findings->dispatches)->toBe([]);
});

it('reports methods whose source cannot be read', function (): void {
    $findings = app(MethodInspector::class)->inspect(
        new ReflectionMethod(ArrayObject::class, 'count'),
        $this->fixtureContext(),
        [],
    );

    expect($findings->readable)->toBeFalse()
        ->and($findings->models)->toBe([]);
});
