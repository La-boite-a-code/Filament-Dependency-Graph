<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use PhpToken;

/**
 * Tokenized PHP file: its namespace, its namespace-level imports and its
 * significant tokens (whitespace and comments removed).
 */
final readonly class SourceFile
{
    /**
     * @param  array<string, string>  $imports  Import alias to fully qualified class.
     * @param  list<PhpToken>  $tokens
     */
    public function __construct(
        public string $namespace,
        public array $imports,
        public array $tokens,
    ) {}
}
