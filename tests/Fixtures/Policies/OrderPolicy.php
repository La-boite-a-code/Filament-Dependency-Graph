<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Policies;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;

/**
 * Discovered through Laravel's naming convention.
 */
final class OrderPolicy
{
    public function before(User $user): ?bool
    {
        return null;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return true;
    }
}
