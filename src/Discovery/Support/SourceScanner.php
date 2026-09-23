<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use LaBoiteACode\DependencyGraph\Support\ClassName;
use PhpToken;
use ReflectionMethod;

/**
 * Token based static analysis shared by the discoverers.
 *
 * Only real tokens are read, so comments and string literals that merely
 * contain code-like text never produce references. Nothing is executed.
 */
final class SourceScanner
{
    private const CLASS_NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

    private const IGNORED_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML];

    /** @var array<string, SourceFile|null> */
    private array $files = [];

    /**
     * Reads and tokenizes a file once per scanner lifetime.
     */
    public function file(string $path): ?SourceFile
    {
        if (array_key_exists($path, $this->files)) {
            return $this->files[$path];
        }

        $source = is_file($path) ? @file_get_contents($path) : false;

        return $this->files[$path] = $source === false ? null : $this->parse($source);
    }

    /**
     * Releases the tokenized files kept in memory.
     */
    public function forget(): void
    {
        $this->files = [];
    }

    public function parse(string $source): SourceFile
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is(self::IGNORED_TOKENS),
        ));

        [$namespace, $imports] = $this->namespaceAndImports($tokens);

        return new SourceFile($namespace, $imports, $tokens);
    }

    /**
     * The body of a method, or null when its source cannot be read or the
     * method has no body.
     */
    public function method(ReflectionMethod $method): ?MethodSource
    {
        $path = $method->getFileName();

        if (! is_string($path)) {
            return null;
        }

        $file = $this->file($path);

        if ($file === null) {
            return null;
        }

        $body = $this->methodBody($file->tokens, $method->getName(), (int) $method->getStartLine());

        return $body === null ? null : new MethodSource($this, $file, $body);
    }

    /**
     * Fully qualified class for a class reference as written in the source.
     * self, static and parent cannot be resolved statically and yield null.
     *
     * @param  array<string, string>  $imports
     */
    public function resolveClass(string $reference, string $namespace, array $imports): ?string
    {
        $reference = trim($reference);

        if ($reference === '' || in_array(strtolower($reference), ['self', 'static', 'parent'], true)) {
            return null;
        }

        if (str_starts_with($reference, '\\')) {
            return ltrim($reference, '\\');
        }

        if (str_starts_with(strtolower($reference), 'namespace\\')) {
            $relative = substr($reference, strlen('namespace\\'));

            return $namespace === '' ? $relative : $namespace . '\\' . $relative;
        }

        [$first, $remaining] = array_pad(explode('\\', $reference, 2), 2, null);

        if (isset($imports[$first])) {
            return $remaining === null ? $imports[$first] : $imports[$first] . '\\' . $remaining;
        }

        return $namespace === '' ? $reference : $namespace . '\\' . $reference;
    }

    /**
     * Class references written before "::", as written in the source.
     *
     * @param  list<PhpToken>  $tokens
     * @return list<array{reference: string, line: int}>
     */
    public function staticClassReferences(array $tokens): array
    {
        $references = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(self::CLASS_NAME_TOKENS) && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)) {
                $references[] = ['reference' => $token->text, 'line' => $token->line];
            }
        }

        return $references;
    }

    /**
     * First literal view name passed to a view() call.
     */
    public function renderedView(SourceFile $file): ?string
    {
        $tokens = $file->tokens;

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_STRING) || strtolower($token->text) !== 'view') {
                continue;
            }

            if (! ($tokens[$index + 1] ?? null)?->is('(')) {
                continue;
            }

            $argument = $tokens[$index + 2] ?? null;

            if ($argument?->is(T_CONSTANT_ENCAPSED_STRING)) {
                return stripcslashes(substr($argument->text, 1, -1));
            }
        }

        return null;
    }

    public static function isClassName(PhpToken $token): bool
    {
        return $token->is(self::CLASS_NAME_TOKENS);
    }

    /**
     * Namespace-level "use" statements are the only ones found at brace
     * depth zero: trait imports live inside class bodies and closure
     * imports inside function bodies.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{0: string, 1: array<string, string>}
     */
    private function namespaceAndImports(array $tokens): array
    {
        $namespace = '';
        $imports = [];
        $depth = 0;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token->is(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;

                continue;
            }

            if ($token->is('}')) {
                $depth = max(0, $depth - 1);

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            if ($token->is(T_NAMESPACE) && $namespace === '') {
                $next = $tokens[$index + 1] ?? null;

                if ($next !== null && $next->is([T_STRING, T_NAME_QUALIFIED])) {
                    $namespace = $next->text;
                }

                continue;
            }

            if (! $token->is(T_USE)) {
                continue;
            }

            $statement = '';

            for ($index++; $index < $count && ! $tokens[$index]->is(';'); $index++) {
                $text = $tokens[$index]->text;
                $statement .= $tokens[$index]->is([T_AS, T_FUNCTION, T_CONST]) ? ' ' . $text . ' ' : $text;
            }

            $imports = [...$imports, ...$this->parseImportStatement(trim($statement))];
        }

        return [$namespace, $imports];
    }

    /**
     * @return array<string, string>
     */
    private function parseImportStatement(string $statement): array
    {
        if (
            $statement === ''
            || str_starts_with($statement, 'function ')
            || str_starts_with($statement, 'const ')
        ) {
            return [];
        }

        $members = preg_match('/^(.+?)\\\\\{(.+)}$/', $statement, $matches) === 1
            ? array_map(
                static fn (string $member): string => rtrim($matches[1], '\\') . '\\' . trim($member),
                explode(',', $matches[2]),
            )
            : array_map('trim', explode(',', $statement));

        $imports = [];

        foreach ($members as $member) {
            $parts = preg_split('/\s+as\s+/i', trim($member), 2) ?: [];
            $class = ltrim(trim($parts[0] ?? ''), '\\');

            if ($class === '') {
                continue;
            }

            $imports[trim($parts[1] ?? '') ?: ClassName::shortName($class)] = $class;
        }

        return $imports;
    }

    /**
     * Tokens between the braces of the named method declared at or after the
     * given line.
     *
     * @param  list<PhpToken>  $tokens
     * @return list<PhpToken>|null
     */
    private function methodBody(array $tokens, string $method, int $startLine): ?array
    {
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! $token->is(T_FUNCTION) || $token->line < $startLine) {
                continue;
            }

            $name = $tokens[$index + 1] ?? null;

            if ($name?->is('&')) {
                $name = $tokens[$index + 2] ?? null;
            }

            if ($name === null || strcasecmp($name->text, $method) !== 0) {
                continue;
            }

            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                if ($tokens[$cursor]->is(';')) {
                    return null;
                }

                if ($tokens[$cursor]->is('{')) {
                    break;
                }
            }

            $body = [];
            $depth = 0;

            for (; $cursor < $count; $cursor++) {
                $current = $tokens[$cursor];

                if ($current->is(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                    $depth++;
                } elseif ($current->is('}')) {
                    $depth--;

                    if ($depth === 0) {
                        return $body;
                    }
                }

                if ($depth > 0 && ! ($depth === 1 && $current->is('{') && $body === [])) {
                    $body[] = $current;
                }
            }

            return $body;
        }

        return null;
    }
}
