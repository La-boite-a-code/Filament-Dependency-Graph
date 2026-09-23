<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Policies\Attributed;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Declares its policy with the attribute; subclasses inherit it.
 */
#[UsePolicy(DocumentPolicy::class)]
class Document extends Model {}
