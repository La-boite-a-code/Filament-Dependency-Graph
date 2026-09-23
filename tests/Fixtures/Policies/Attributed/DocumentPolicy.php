<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Policies\Attributed;

final class DocumentPolicy
{
    public function view(): bool
    {
        return true;
    }
}
