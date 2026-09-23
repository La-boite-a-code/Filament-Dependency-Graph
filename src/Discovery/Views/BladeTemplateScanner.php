<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

use FilesystemIterator;
use Illuminate\Support\Str;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\Views\ViewReference;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Reads Blade templates as text. Directives and component tags are
 * extracted with their line; nothing is compiled, so application-defined
 * directives and precompilers never run.
 */
final class BladeTemplateScanner implements CollectsDiscoveryWarnings
{
    /**
     * Livewire 4 marks single-file components with this character, possibly
     * followed by a variation selector.
     */
    private const ZAP = '/⚡[\x{FE0E}\x{FE0F}]?/u';

    private const DIRECTIVES = [
        'extendsFirst' => ViewReference::TYPE_EXTENDS,
        'extends' => ViewReference::TYPE_EXTENDS,
        'includeUnless' => ViewReference::TYPE_INCLUDE,
        'includeWhen' => ViewReference::TYPE_INCLUDE,
        'includeFirst' => ViewReference::TYPE_INCLUDE,
        'includeIf' => ViewReference::TYPE_INCLUDE,
        'include' => ViewReference::TYPE_INCLUDE,
        'each' => ViewReference::TYPE_INCLUDE,
        'componentFirst' => ViewReference::TYPE_INCLUDE,
        'component' => ViewReference::TYPE_INCLUDE,
        'livewire' => ViewReference::TYPE_LIVEWIRE,
    ];

    /** Livewire tags that are not components. */
    private const LIVEWIRE_NON_COMPONENTS = ['styles', 'scripts'];

    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly Factory $views,
    ) {}

    /**
     * Application templates: every Blade file under the view paths, keyed
     * by view name. The first path wins, as in the view finder.
     *
     * @return array<string, string> View name to absolute path.
     */
    public function templates(DiscoveryContext $context): array
    {
        $templates = [];
        // Livewire registers folders nested in resources/views as view
        // locations: a file is only listed under the first root holding it.
        $seen = [];

        foreach ($this->viewPaths() as $root) {
            $root = rtrim($root, '/\\');

            if (! is_dir($root)) {
                continue;
            }

            try {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                );

                foreach ($files as $file) {
                    if (! $file instanceof SplFileInfo || ! str_ends_with($file->getFilename(), '.blade.php')) {
                        continue;
                    }

                    $real = $file->getRealPath() ?: $file->getPathname();

                    if (isset($seen[$real])) {
                        continue;
                    }

                    $seen[$real] = true;
                    $relative = substr($file->getPathname(), strlen($root) + 1);

                    if (! $context->includeVendorViewOverrides && str_starts_with($relative, 'vendor' . DIRECTORY_SEPARATOR)) {
                        continue;
                    }

                    $name = $this->nameFromPath($relative);

                    if ($context->excludedViews !== [] && Str::is($context->excludedViews, $name)) {
                        continue;
                    }

                    $templates[$name] ??= $file->getPathname();
                }
            } catch (Throwable $exception) {
                $this->warnings[] = new DiscoveryWarning(
                    type: 'view_path_not_readable',
                    message: sprintf('The view path [%s] could not be read: %s', $root, $exception->getMessage()),
                    exceptionClass: $exception::class,
                );
            }
        }

        ksort($templates, SORT_STRING);

        return $templates;
    }

    /**
     * View name for a path relative to a view directory.
     */
    public function nameFromPath(string $relative): string
    {
        $name = (string) preg_replace(self::ZAP, '', $relative);
        $name = substr($name, 0, -strlen('.blade.php'));

        return str_replace(['/', '\\'], '.', $name);
    }

    /**
     * @return list<array{type: string, written: string|null, directive: string, expression: string|null, line: int}>
     */
    public function references(string $contents): array
    {
        $contents = $this->neutralize($contents);
        $references = [...$this->directives($contents), ...$this->tags($contents)];

        usort($references, static fn (array $a, array $b): int => [$a['line'], $a['directive']] <=> [$b['line'], $b['directive']]);

        return $references;
    }

    /**
     * Whether the template is a Livewire single-file component: its PHP
     * block declares an anonymous component class.
     */
    public function isLivewireSingleFile(string $contents): bool
    {
        return preg_match('/<\?php.*new\s+(?:#\[[^\]]*\]\s*)*class\b.*extends\s+[\\\\\w]*Component\b/s', $contents) === 1;
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function viewPaths(): array
    {
        try {
            $finder = $this->views->getFinder();
        } catch (Throwable) {
            return [];
        }

        return $finder instanceof FileViewFinder ? array_values($finder->getPaths()) : [];
    }

    /**
     * Blanks out comments, @verbatim blocks and PHP blocks while keeping
     * every line break, so offsets still map to the right lines.
     */
    private function neutralize(string $contents): string
    {
        return (string) preg_replace_callback(
            '/\{\{--.*?--\}\}|@verbatim\b.*?@endverbatim\b|@php\b.*?@endphp\b|<\?php.*?(\?>|$)/s',
            static fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]),
            $contents,
        );
    }

    /**
     * @return list<array{type: string, written: string|null, directive: string, expression: string|null, line: int}>
     */
    private function directives(string $contents): array
    {
        // Blade only compiles a directive that does not follow a word
        // character or another @, with blanks but no line break before "(".
        $pattern = '/(?<![\w@])@(' . implode('|', array_keys(self::DIRECTIVES)) . ')[ \t]*\(/';
        $references = [];

        preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $directive = $match[1][0];
            $offset = $match[0][1];
            $arguments = $this->arguments($contents, $offset + strlen($match[0][0]) - 1);
            $line = substr_count($contents, "\n", 0, $offset) + 1;
            $type = self::DIRECTIVES[$directive];

            $candidates = match ($directive) {
                'includeWhen', 'includeUnless' => [$arguments[1] ?? null],
                'includeFirst', 'extendsFirst', 'componentFirst' => $this->arrayItems($arguments[0] ?? null),
                'each' => [$arguments[0] ?? null, ...(isset($arguments[3]) && ! str_contains($arguments[3], 'raw|') ? [$arguments[3]] : [])],
                default => [$arguments[0] ?? null],
            };

            foreach ($candidates as $argument) {
                if ($argument === null || trim($argument) === '') {
                    continue;
                }

                $literal = $this->literal($argument);
                $class = $literal === null && in_array($directive, ['livewire', 'component', 'componentFirst'], true)
                    ? $this->classConstant($argument)
                    : null;
                $written = $literal ?? $class;

                $references[] = [
                    'type' => match (true) {
                        $written === null => ViewReference::TYPE_DYNAMIC,
                        // @component(Alert::class) renders a class component.
                        $class !== null && $directive !== 'livewire' => ViewReference::TYPE_COMPONENT,
                        default => $type,
                    },
                    'written' => $written,
                    'directive' => '@' . $directive,
                    'expression' => $written === null ? trim($argument) : null,
                    'line' => $line,
                ];
            }
        }

        return $references;
    }

    /**
     * @return list<array{type: string, written: string|null, directive: string, expression: string|null, line: int}>
     */
    private function tags(string $contents): array
    {
        $references = [];

        preg_match_all('/<(x[-:]|livewire:)([\w\-:.]+)([^>]*)>/', $contents, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $prefix = $match[1][0];
            $name = $match[2][0];
            $attributes = $match[3][0];
            $line = substr_count($contents, "\n", 0, $match[0][1]) + 1;
            $livewire = $prefix === 'livewire:';

            if (! $livewire && ($name === 'slot' || str_starts_with($name, 'slot:'))) {
                continue;
            }

            if ($livewire && in_array($name, self::LIVEWIRE_NON_COMPONENTS, true)) {
                continue;
            }

            $directive = '<' . $prefix . $name . '>';

            if ($name === 'dynamic-component' || ($livewire && $name === 'is')) {
                $matched = preg_match('/(?<![\w-])(:?)(?:component|is)\s*=\s*"([^"]*)"/', $attributes, $component) === 1;
                $value = $matched ? $component[2] : '';
                // component="alert" without a colon is a plain string.
                $static = $matched && $component[1] === '' && $value !== '';

                $references[] = [
                    'type' => $static ? ($livewire ? ViewReference::TYPE_LIVEWIRE : ViewReference::TYPE_COMPONENT) : ViewReference::TYPE_DYNAMIC,
                    'written' => $static ? $value : null,
                    'directive' => $directive,
                    'expression' => $static ? null : $value,
                    'line' => $line,
                ];

                continue;
            }

            $references[] = [
                'type' => $livewire ? ViewReference::TYPE_LIVEWIRE : ViewReference::TYPE_COMPONENT,
                'written' => $name,
                'directive' => $directive,
                'expression' => null,
                'line' => $line,
            ];
        }

        return $references;
    }

    /**
     * Top-level arguments of the call whose opening parenthesis is at the
     * given offset, as written.
     *
     * @return list<string>
     */
    private function arguments(string $contents, int $open): array
    {
        $arguments = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($contents);

        for ($index = $open + 1; $index < $length; $index++) {
            $character = $contents[$index];

            if ($quote !== null) {
                $current .= $character;

                if ($character === '\\') {
                    $current .= $contents[++$index] ?? '';
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '\'' || $character === '"') {
                $quote = $character;
                $current .= $character;

                continue;
            }

            if ($character === '(' || $character === '[' || $character === '{') {
                $depth++;
            } elseif ($character === ')' || $character === ']' || $character === '}') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $arguments[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        if (trim($current) !== '') {
            $arguments[] = trim($current);
        }

        return $arguments;
    }

    /**
     * Items of an array literal such as ['a', 'b'], or the value itself
     * when it is not an array literal.
     *
     * @return list<string|null>
     */
    private function arrayItems(?string $argument): array
    {
        if ($argument === null) {
            return [];
        }

        $argument = trim($argument);

        if (! str_starts_with($argument, '[') || ! str_ends_with($argument, ']')) {
            return [$argument];
        }

        return array_map('trim', $this->arguments('(' . substr($argument, 1, -1) . ')', 0));
    }

    private function literal(string $argument): ?string
    {
        $argument = trim($argument);

        if (preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'$/s', $argument, $match) === 1) {
            return stripslashes($match[1]);
        }

        if (preg_match('/^"([^"$\\\\]*)"$/', $argument, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function classConstant(string $argument): ?string
    {
        return preg_match('/^\\\\?([\w\\\\]+)::class$/', trim($argument), $match) === 1 ? $match[1] : null;
    }
}
