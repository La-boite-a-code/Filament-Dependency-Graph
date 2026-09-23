<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Actions;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ShipOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

final class PlaceOrder
{
    public function execute(): void
    {
        $order = Order::query()->create();

        event(new OrderPlaced);
        ShipOrder::dispatch($order);
    }
}
