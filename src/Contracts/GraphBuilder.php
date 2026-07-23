<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Contracts;

use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;

interface GraphBuilder
{
    public function build(ApplicationSnapshot $snapshot): Graph;
}
