<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class BrokenModel extends Model
{
    public function __construct(array $attributes = [])
    {
        throw new RuntimeException('This model cannot be constructed.');
    }
}
