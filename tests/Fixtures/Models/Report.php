<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Report extends Model
{
    public function author(): BelongsTo
    {
        throw new RuntimeException('Relation discovery should survive this.');
    }

    public function missingTarget(): BelongsTo
    {
        return $this->belongsTo('LaBoiteACode\DependencyGraph\Tests\Fixtures\Missing\Target', 'target_id');
    }
}
