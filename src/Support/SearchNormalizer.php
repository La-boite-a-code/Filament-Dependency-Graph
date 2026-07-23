<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Support;

/**
 * Normalization applied to both indexed values and search queries.
 *
 * The rules follow the specification: lowercase, accent folding, namespace
 * separators treated as spaces, camel case split, hyphens and underscores
 * treated as spaces.
 */
final class SearchNormalizer
{
    private const ACCENT_MAP = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i',
        'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
        'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'æ' => 'ae', 'œ' => 'oe',
        'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
        'Ç' => 'c', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Ì' => 'i',
        'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'Ñ' => 'n', 'Ò' => 'o', 'Ó' => 'o',
        'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o', 'Ø' => 'o', 'Ù' => 'u', 'Ú' => 'u',
        'Û' => 'u', 'Ü' => 'u', 'Ý' => 'y',
    ];

    public static function normalize(string $value): string
    {
        $value = strtr($value, self::ACCENT_MAP);

        $value = str_replace(['\\', '/', '::', ':'], ' ', $value);

        $value = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $value);
        $value = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value);

        $value = str_replace(['-', '_', '.'], ' ', $value);

        $value = strtolower($value);
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        return explode(' ', $normalized);
    }
}
