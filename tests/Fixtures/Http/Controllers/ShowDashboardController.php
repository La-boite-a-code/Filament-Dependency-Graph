<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;

final class ShowDashboardController
{
    public function __invoke(): string
    {
        return (string) Product::query()->count();
    }
}
