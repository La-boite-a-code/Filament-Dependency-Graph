<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Exceptions;

final class NodeNotFoundException extends DependencyGraphException
{
    public static function withId(string $nodeId): self
    {
        return new self(sprintf('Node [%s] was not found in the graph.', $nodeId));
    }
}
