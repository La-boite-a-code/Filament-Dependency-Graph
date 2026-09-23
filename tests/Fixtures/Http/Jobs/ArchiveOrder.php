<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;

final class ArchiveOrder implements ShouldQueue
{
    use Dispatchable;

    public function __construct(
        public Order $order,
    ) {}

    public function handle(): void {}
}
