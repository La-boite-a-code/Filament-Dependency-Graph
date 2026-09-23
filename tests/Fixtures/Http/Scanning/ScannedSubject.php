<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning;

use Illuminate\Support\Facades\Mail;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\{Order, Product as Item};
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User as Account;

final class ScannedSubject
{
    use ScannedTrait;

    public function __construct(
        private readonly object $orders,
    ) {}

    public function handle(object $action, callable $callback): void
    {
        // Customer::query() is a comment and must be ignored.
        $text = 'Invoice::query() inside a string';

        Order::query();
        Item::where('id', 1);
        $class = Account::class;

        Mail::to('someone@example.com')->send(new ShippedMail);
        \LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning\ShippedJob::dispatch();
        new namespace\ShippedJob;
        $anonymous = new class {};
        $dynamic = new $class;

        $action->execute();
        $action?->undo();
        $this->orders->create();
        $callback();
        static::helper();

        $closure = function () use ($action): void {
            $action->execute();
        };
    }

    public function empty(): void {}

    private static function helper(): void {}
}
