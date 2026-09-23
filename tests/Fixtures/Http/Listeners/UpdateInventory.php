<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderArchived;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events\OrderPlaced;

final class UpdateInventory implements ShouldQueue
{
    public function handle(OrderPlaced $event): void {}

    public function release(OrderArchived $event): void {}
}
