<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;

final class NotifyWarehouse
{
    use Dispatchable;

    public function handle(): void {}
}
