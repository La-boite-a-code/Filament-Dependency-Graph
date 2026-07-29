<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class ModelWithoutPrimaryKey extends Model
{
    public $incrementing = false;

    protected $primaryKey = false;

    protected $table = 'reporting_view';
}
