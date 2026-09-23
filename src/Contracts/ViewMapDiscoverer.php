<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\Http\HttpMapData;
use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewMapData;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;

interface ViewMapDiscoverer
{
    /**
     * Maps the application templates, the classes and routes rendering
     * them, and what they include, extend and render in turn.
     *
     * @param  list<LivewireComponentData>  $livewireComponents
     */
    public function discover(DiscoveryContext $context, array $livewireComponents, HttpMapData $http): ViewMapData;
}
