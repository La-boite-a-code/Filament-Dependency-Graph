<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Commands;

use Illuminate\Console\Command;
use LaBoiteACode\DependencyGraph\Application\ClearDependencyGraphCache;

final class ClearDependencyGraphCacheCommand extends Command
{
    protected $signature = 'filament-dependency-graph:clear';

    protected $description = 'Clear every cached dependency graph snapshot';

    public function handle(ClearDependencyGraphCache $clearCache): int
    {
        $clearCache->execute();

        $this->components->info('Dependency graph cache cleared.');

        return self::SUCCESS;
    }
}
