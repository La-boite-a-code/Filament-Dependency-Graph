<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    public $timestamps = false;

    protected $connection = 'audit';

    protected $table = 'audit_entries';

    protected $primaryKey = 'entry_id';

    protected $guarded = ['*'];
}
