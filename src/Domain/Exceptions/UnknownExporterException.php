<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Exceptions;

final class UnknownExporterException extends DependencyGraphException
{
    /**
     * @param  list<string>  $availableFormats
     */
    public static function forFormat(string $format, array $availableFormats): self
    {
        return new self(sprintf(
            'Unknown export format [%s]. Available formats: %s.',
            $format,
            $availableFormats === [] ? 'none' : implode(', ', $availableFormats),
        ));
    }
}
