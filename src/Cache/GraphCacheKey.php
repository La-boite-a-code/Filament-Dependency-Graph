<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Cache;

use LaBoiteACode\DependencyGraph\Domain\SchemaVersion;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use Stringable;

final readonly class GraphCacheKey implements Stringable
{
    private const PREFIX = 'filament-dependency-graph:snapshot:';

    public function __construct(
        public string $value,
    ) {}

    /**
     * The key covers every input that can change discovery output: the full
     * discovery context, runtime versions, the application environment and
     * the serialization schema version.
     */
    public static function create(
        DiscoveryContext $context,
        string $applicationEnvironment,
        string $laravelVersion,
        string $filamentVersion,
        string $phpVersion,
        string $schemaVersion = SchemaVersion::CURRENT,
    ): self {
        $payload = json_encode([
            'context' => $context->toArray(),
            'environment' => $applicationEnvironment,
            'laravel' => $laravelVersion,
            'filament' => $filamentVersion,
            'php' => $phpVersion,
            'schema' => $schemaVersion,
        ]);

        return new self(self::PREFIX . sha1($payload === false ? '' : $payload));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
