<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Export;

use DateTimeInterface;
use JsonException;
use LaBoiteACode\DependencyGraph\Contracts\GraphExporter;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\GraphSerializationException;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\SchemaVersion;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;

/**
 * Canonical machine-readable export. Ordering comes from the graph itself,
 * which is already deterministic, and the payload never contains secrets,
 * credentials or record data.
 */
final class JsonGraphExporter implements GraphExporter
{
    public function format(): string
    {
        return 'json';
    }

    public function export(Graph $graph, ExportOptions $options): string
    {
        $serialized = $graph->toArray();

        $payload = [
            'schemaVersion' => SchemaVersion::CURRENT,
            'generatedAt' => $options->generatedAt?->format(DateTimeInterface::ATOM),
            'environment' => $options->environment,
            'scope' => $options->scope,
            'filters' => $options->filters,
            'nodes' => $serialized['nodes'],
            'edges' => $serialized['edges'],
            'warnings' => array_map(
                static fn (DiscoveryWarning $warning): array => $warning->toArray(),
                $options->warnings,
            ),
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        if ($options->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($payload, $flags);
        } catch (JsonException $exception) {
            throw GraphSerializationException::because($exception->getMessage());
        }
    }
}
