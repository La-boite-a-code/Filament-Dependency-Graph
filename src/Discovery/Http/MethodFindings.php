<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;

/**
 * What a static reading of one method found.
 */
final readonly class MethodFindings
{
    /**
     * @param  list<string>  $models  Model classes referenced statically.
     * @param  list<DispatchReference>  $dispatches
     * @param  list<string>  $views  View names rendered by the method itself.
     */
    public function __construct(
        public array $models,
        public array $dispatches,
        public bool $readable,
        public array $views = [],
    ) {}
}
