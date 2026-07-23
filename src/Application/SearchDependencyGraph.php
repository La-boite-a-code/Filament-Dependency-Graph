<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Application;

use LaBoiteACode\DependencyGraph\Domain\DTO\SearchResultData;
use LaBoiteACode\DependencyGraph\Domain\Enums\EdgeType;
use LaBoiteACode\DependencyGraph\Domain\Graph\Graph;
use LaBoiteACode\DependencyGraph\Domain\Graph\Node;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\SearchNormalizer;

/**
 * In-memory node search with deterministic ranking: exact label, exact
 * short class, prefix, exact table, contains, then metadata matches.
 */
final class SearchDependencyGraph
{
    private const SCORE_EXACT_LABEL = 100;

    private const SCORE_EXACT_SHORT_CLASS = 90;

    private const SCORE_PREFIX = 75;

    private const SCORE_EXACT_TABLE = 70;

    private const SCORE_CONTAINS = 50;

    private const SCORE_METADATA = 30;

    /**
     * @return list<SearchResultData>
     */
    public function execute(Graph $graph, string $query, int $limit = 20): array
    {
        $normalizedQuery = SearchNormalizer::normalize($query);

        if ($normalizedQuery === '' || $limit < 1) {
            return [];
        }

        $results = [];

        foreach ($graph->nodes as $node) {
            $match = $this->score($graph, $node, $normalizedQuery);

            if ($match === null) {
                continue;
            }

            [$score, $matchedField] = $match;

            $results[] = new SearchResultData(
                nodeId: $node->id->value,
                type: $node->type,
                label: $node->label,
                subtitle: $node->subtitle,
                score: $score,
                matchedField: $matchedField,
            );
        }

        usort($results, static function (SearchResultData $a, SearchResultData $b): int {
            return [-$a->score, strtolower($a->label), $a->nodeId]
                <=> [-$b->score, strtolower($b->label), $b->nodeId];
        });

        return array_slice($results, 0, $limit);
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private function score(Graph $graph, Node $node, string $query): ?array
    {
        $label = SearchNormalizer::normalize($node->label);

        $class = $this->metadataString($node, 'class');
        $shortClass = $class === '' ? '' : SearchNormalizer::normalize(ClassName::shortName($class));
        $normalizedClass = $class === '' ? '' : SearchNormalizer::normalize($class);

        $table = SearchNormalizer::normalize($this->metadataString($node, 'table'));

        if ($label === $query) {
            return [self::SCORE_EXACT_LABEL, 'label'];
        }

        if ($shortClass !== '' && $shortClass === $query) {
            return [self::SCORE_EXACT_SHORT_CLASS, 'class'];
        }

        foreach (['label' => $label, 'class' => $shortClass, 'full_class' => $normalizedClass] as $field => $value) {
            if ($value !== '' && str_starts_with($value, $query)) {
                return [self::SCORE_PREFIX, $field];
            }
        }

        if ($table !== '' && $table === $query) {
            return [self::SCORE_EXACT_TABLE, 'table'];
        }

        $primary = [
            'label' => $label,
            'full_class' => $normalizedClass,
            'table' => $table,
            'subtitle' => SearchNormalizer::normalize((string) $node->subtitle),
        ];

        foreach ($primary as $field => $value) {
            if ($value !== '' && str_contains($value, $query)) {
                return [self::SCORE_CONTAINS, $field];
            }
        }

        $metadata = [
            'panel' => $this->metadataString($node, 'panel_id'),
            'navigation_group' => $this->metadataString($node, 'navigation_group'),
            'namespace' => $this->metadataString($node, 'namespace'),
            'relation_methods' => implode(' ', $this->relationMethods($graph, $node)),
            'badges' => implode(' ', $node->badges),
        ];

        foreach ($metadata as $field => $value) {
            $value = SearchNormalizer::normalize($value);

            if ($value !== '' && str_contains($value, $query)) {
                return [self::SCORE_METADATA, $field];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function relationMethods(Graph $graph, Node $node): array
    {
        $methods = [];

        foreach ($graph->outgoingEdges($node->id) as $edge) {
            if ($edge->type === EdgeType::ModelRelation) {
                $methods[] = $edge->label;
            }
        }

        return $methods;
    }

    private function metadataString(Node $node, string $key): string
    {
        $value = $node->metadata[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
