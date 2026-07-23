<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Export;

use LaBoiteACode\DependencyGraph\Contracts\GraphExporter;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\UnknownExporterException;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\ExportOptions;

final class ExportManager
{
    /** @var array<string, GraphExporter> */
    private array $exporters = [];

    /**
     * @param  iterable<GraphExporter>  $exporters
     */
    public function __construct(iterable $exporters = [])
    {
        foreach ($exporters as $exporter) {
            $this->register($exporter);
        }
    }

    public function register(GraphExporter $exporter): void
    {
        $this->exporters[$exporter->format()] = $exporter;
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        $formats = array_keys($this->exporters);
        sort($formats, SORT_STRING);

        return $formats;
    }

    public function export(string $format, Graph $graph, ExportOptions $options): string
    {
        $exporter = $this->exporters[$format]
            ?? throw UnknownExporterException::forFormat($format, $this->formats());

        return $exporter->export($graph, $options);
    }
}
