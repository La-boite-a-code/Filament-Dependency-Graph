<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Listened to, but never dispatched by the application.
 */
final class OrderArchived
{
    use Dispatchable;
}
