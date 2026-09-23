<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

final class ShipOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;

    public function __construct(
        public Order $order,
    ) {}

    public function handle(): void
    {
        NotifyWarehouse::dispatch();
    }
}
