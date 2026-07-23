<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Commands;

use Illuminate\Console\Command;
use LaBoiteACode\DependencyGraph\Application\DefaultDependencyGraphManager;
use LaBoiteACode\DependencyGraph\Contracts\DependencyGraphManager;
use LaBoiteACode\DependencyGraph\Domain\Enums\GraphScope;
use LaBoiteACode\DependencyGraph\Domain\Enums\TraversalDirection;
use LaBoiteACode\DependencyGraph\Domain\Exceptions\UnknownExporterException;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\GraphQuery;
use Throwable;

final class ExportDependencyGraphCommand extends Command
{
    protected $signature = 'filament-dependency-graph:export
        {--format=json : Export format, json or mermaid}
        {--scope= : Graph scope, filament or laravel}
        {--panel=* : Only include the given panel ids}
        {--focus= : Focus on a node id, for example model:app.models.order}
        {--depth= : Focus traversal depth}
        {--direction=both : Focus direction, incoming, outgoing or both}
        {--output= : Write the export to this file instead of standard output}
        {--force : Overwrite the output file when it exists}';

    protected $description = 'Export the dependency graph as JSON or Mermaid';

    public function handle(DependencyGraphManager $manager): int
    {
        $formatOption = $this->option('format');
        $format = is_string($formatOption) && $formatOption !== '' ? $formatOption : 'json';

        try {
            $query = $this->buildQuery($manager);
            $content = $manager->export($format, $query);
        } catch (UnknownExporterException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error(sprintf('Export failed: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->line($content);

            return self::SUCCESS;
        }

        if (file_exists($output) && ! $this->option('force')) {
            $this->components->error(sprintf('File [%s] already exists. Use --force to overwrite it.', $output));

            return self::FAILURE;
        }

        $directory = dirname($output);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->components->error(sprintf('Directory [%s] could not be created.', $directory));

            return self::FAILURE;
        }

        if (file_put_contents($output, $content) === false) {
            $this->components->error(sprintf('File [%s] could not be written.', $output));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Dependency graph exported to [%s].', $output));

        return self::SUCCESS;
    }

    private function buildQuery(DependencyGraphManager $manager): GraphQuery
    {
        $defaults = $manager instanceof DefaultDependencyGraphManager
            ? $manager->defaultQuery()
            : new GraphQuery;

        $scopeOption = $this->option('scope');
        $scope = is_string($scopeOption) && $scopeOption !== ''
            ? GraphScope::from($scopeOption)
            : $defaults->scope;

        /** @var list<string> $panels */
        $panels = array_values(array_filter((array) $this->option('panel'), 'is_string'));

        $focus = $this->option('focus');
        $depthOption = $this->option('depth');

        $directionOption = $this->option('direction');
        $direction = TraversalDirection::tryFrom(is_string($directionOption) ? $directionOption : 'both')
            ?? TraversalDirection::Both;

        return new GraphQuery(
            scope: $scope,
            panelIds: $panels,
            focusNodeId: is_string($focus) && $focus !== '' ? $focus : null,
            depth: is_numeric($depthOption) ? (int) $depthOption : null,
            direction: $direction,
            includeOrphans: $defaults->includeOrphans,
        );
    }
}
