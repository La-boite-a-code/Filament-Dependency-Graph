<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Facades;

use Illuminate\Support\Facades\Facade;
use LaBoiteACode\DependencyGraph\Contracts\DependencyGraphManager;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;

/**
 * @method static ApplicationSnapshot discover(DiscoveryContext|null $context = null)
 * @method static Graph graph(GraphQuery|null $query = null)
 * @method static string export(string $format, GraphQuery|null $query = null, ExportOptions|null $options = null)
 * @method static void clearCache()
 *
 * @see \LaBoiteACode\DependencyGraph\Application\DefaultDependencyGraphManager
 */
final class DependencyGraph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DependencyGraphManager::class;
    }
}
