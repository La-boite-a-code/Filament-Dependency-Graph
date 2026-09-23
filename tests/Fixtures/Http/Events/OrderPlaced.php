<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class OrderPlaced
{
    use Dispatchable;
}
