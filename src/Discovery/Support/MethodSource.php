<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use PhpToken;

/**
 * Significant tokens of one method body, with class names resolved against
 * the imports of the file that declares the method.
 */
final readonly class MethodSource
{
    /**
     * @param  list<PhpToken>  $tokens
     */
    public function __construct(
        private SourceScanner $scanner,
        private SourceFile $file,
        private array $tokens,
    ) {}

    /**
     * Classes referenced before "::" (static calls, constants, ::class).
     *
     * @return list<array{class: string, line: int}>
     */
    public function staticClassReferences(): array
    {
        $references = [];

        foreach ($this->scanner->staticClassReferences($this->tokens) as $reference) {
            $class = $this->resolve($reference['reference']);

            if ($class !== null) {
                $references[] = ['class' => $class, 'line' => $reference['line']];
            }
        }

        return $references;
    }

    /**
     * Static method calls such as "OrderShipped::dispatch(".
     *
     * @return list<array{class: string, method: string, line: int}>
     */
    public function staticCalls(): array
    {
        $calls = [];

        foreach ($this->tokens as $index => $token) {
            if (
                ! SourceScanner::isClassName($token)
                || ! $this->at($index + 1, T_DOUBLE_COLON)
                || ! $this->at($index + 2, T_STRING)
                || ! $this->at($index + 3, '(')
            ) {
                continue;
            }

            $class = $this->resolve($token->text);

            if ($class !== null) {
                $calls[] = ['class' => $class, 'method' => $this->tokens[$index + 2]->text, 'line' => $token->line];
            }
        }

        return $calls;
    }

    /**
     * Named class instantiations such as "new OrderShipped(".
     *
     * @return list<array{class: string, line: int}>
     */
    public function instantiations(): array
    {
        $instantiations = [];

        foreach ($this->tokens as $index => $token) {
            if (! $token->is(T_NEW)) {
                continue;
            }

            $name = $this->tokens[$index + 1] ?? null;

            if ($name === null || ! SourceScanner::isClassName($name)) {
                continue;
            }

            $class = $this->resolve($name->text);

            if ($class !== null) {
                $instantiations[] = ['class' => $class, 'line' => $token->line];
            }
        }

        return $instantiations;
    }

    /**
     * Calls on collaborators: "$action->handle(", "$this->orders->create("
     * and "$action(" (mapped to __invoke).
     *
     * @return list<array{variable: string|null, property: string|null, method: string, line: int}>
     */
    public function collaboratorCalls(): array
    {
        $calls = [];

        foreach ($this->tokens as $index => $token) {
            if (! $token->is(T_VARIABLE)) {
                continue;
            }

            if ($token->text === '$this') {
                if (
                    $this->at($index + 1, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
                    && $this->at($index + 2, T_STRING)
                    && $this->at($index + 3, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
                    && $this->at($index + 4, T_STRING)
                    && $this->at($index + 5, '(')
                ) {
                    $calls[] = [
                        'variable' => null,
                        'property' => $this->tokens[$index + 2]->text,
                        'method' => $this->tokens[$index + 4]->text,
                        'line' => $token->line,
                    ];
                }

                continue;
            }

            $variable = substr($token->text, 1);

            if (
                $this->at($index + 1, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
                && $this->at($index + 2, T_STRING)
                && $this->at($index + 3, '(')
            ) {
                $calls[] = [
                    'variable' => $variable,
                    'property' => null,
                    'method' => $this->tokens[$index + 2]->text,
                    'line' => $token->line,
                ];

                continue;
            }

            if ($this->at($index + 1, '(')) {
                $calls[] = ['variable' => $variable, 'property' => null, 'method' => '__invoke', 'line' => $token->line];
            }
        }

        return $calls;
    }

    private function resolve(string $reference): ?string
    {
        return $this->scanner->resolveClass($reference, $this->file->namespace, $this->file->imports);
    }

    /**
     * @param  int|string|array<int|string>  $kind
     */
    private function at(int $index, int|string|array $kind): bool
    {
        return isset($this->tokens[$index]) && $this->tokens[$index]->is($kind);
    }
}
