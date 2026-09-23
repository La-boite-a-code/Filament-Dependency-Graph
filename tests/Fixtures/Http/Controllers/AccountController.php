<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers;

use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Attributes\Controllers\WithoutMiddleware;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\OrderItem;

/**
 * Declares its middleware through the framework attributes (Laravel 13+).
 */
#[Middleware('verified', only: ['update'])]
final class AccountController
{
    public function show(OrderItem $orderItem): string
    {
        return (string) $orderItem->getKey();
    }

    #[Middleware('password.confirm')]
    #[WithoutMiddleware('throttle')]
    public function update(): string
    {
        return 'updated';
    }
}
