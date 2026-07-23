<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Models;

class NotAModel
{
    public function describe(): string
    {
        return 'This class is not an Eloquent model.';
    }
}
