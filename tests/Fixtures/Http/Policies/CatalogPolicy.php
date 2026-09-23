<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Policies;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;

/**
 * Registered explicitly for the Product model.
 */
final class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
