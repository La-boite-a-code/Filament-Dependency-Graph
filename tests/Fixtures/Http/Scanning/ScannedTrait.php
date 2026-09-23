<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Scanning;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Invoice;

trait ScannedTrait
{
    public function fromTrait(): void
    {
        Invoice::query();
    }
}
