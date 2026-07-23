<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Exceptions;

final class GraphSerializationException extends DependencyGraphException
{
    public static function because(string $reason): self
    {
        return new self(sprintf('The graph could not be serialized: %s', $reason));
    }
}
