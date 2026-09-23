<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Http\Controllers;

use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\AuditEntry;

/**
 * The only application code using AuditEntry, a model without relations.
 */
final class AuditController
{
    public function index(): string
    {
        return (string) AuditEntry::query()->count();
    }
}
