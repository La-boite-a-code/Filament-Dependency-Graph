<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Repositories;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs\ArchiveOrder;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

final class OrderRepository
{
    public function archive(Order $order): void
    {
        ArchiveOrder::dispatch($order);
    }

    public function unused(): void
    {
        // Never called from a controller: must not be followed.
        ArchiveOrder::dispatchSync(new Order);
    }
}
