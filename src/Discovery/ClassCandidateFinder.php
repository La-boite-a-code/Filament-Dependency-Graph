<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use FilesystemIterator;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Finds class candidates by scanning PHP source files.
 *
 * Files are tokenized instead of included, so scanning never executes
 * application code. The resulting class list is sorted to keep discovery
 * deterministic regardless of filesystem ordering.
 */
final class ClassCandidateFinder
{
    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function fromPaths(array $paths): array
    {
        $classes = [];

        foreach ($paths as $path) {
            foreach ($this->phpFilesIn($path) as $file) {
                foreach ($this->classesInFile($file) as $class) {
                    $classes[$class] = true;
                }
            }
        }

        $classes = array_keys($classes);
        sort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * Finds candidates for the given namespaces through the Composer PSR-4
     * autoload map, used for opt-in vendor model discovery.
     *
     * @param  list<string>  $namespaces
     * @return list<string>
     */
    public function fromComposerNamespaces(array $namespaces, string $vendorPath): array
    {
        if ($namespaces === [] || $vendorPath === '') {
            return [];
        }

        $autoloadFile = rtrim($vendorPath, '/\\') . '/composer/autoload_psr4.php';

        if (! is_file($autoloadFile)) {
            return [];
        }

        try {
            /** @var array<string, list<string>> $map */
            $map = require $autoloadFile;
        } catch (Throwable) {
            return [];
        }

        $paths = [];

        foreach ($map as $prefix => $directories) {
            foreach ($namespaces as $namespace) {
                $wanted = rtrim($namespace, '\\') . '\\';

                if (! str_starts_with($wanted, $prefix) && ! str_starts_with($prefix, $wanted)) {
                    continue;
                }

                foreach ($directories as $directory) {
                    $paths[] = str_starts_with($wanted, $prefix) && strlen($wanted) > strlen($prefix)
                        ? $directory . '/' . str_replace('\\', '/', rtrim(substr($wanted, strlen($prefix)), '\\'))
                        : $directory;
                }
            }
        }

        $classes = array_values(array_filter(
            $this->fromPaths(array_values(array_unique($paths))),
            static fn (string $class): bool => NamespaceMatcher::matchesNamespace($class, $namespaces),
        ));

        sort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Extracts fully qualified class names declared in a file without
     * executing it.
     *
     * @return list<string>
     */
    private function classesInFile(string $file): array
    {
        $contents = @file_get_contents($file);

        if ($contents === false) {
            return [];
        }

        $tokens = token_get_all($contents);
        $count = count($tokens);

        $namespace = '';
        $classes = [];

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $index, $count);

                continue;
            }

            if ($token[0] !== T_CLASS) {
                continue;
            }

            if ($this->isNonDeclarationClassToken($tokens, $index)) {
                continue;
            }

            $name = $this->readClassName($tokens, $index, $count);

            if ($name !== null) {
                $classes[] = $namespace === '' ? $name : $namespace . '\\' . $name;
            }
        }

        return $classes;
    }

    /**
     * @param  list<mixed>  $tokens
     */
    private function readNamespace(array $tokens, int $index, int $count): string
    {
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_STRING], true)) {
                return $token[1];
            }

            if ($token === ';' || $token === '{') {
                break;
            }
        }

        return '';
    }

    /**
     * @param  list<mixed>  $tokens
     */
    private function readClassName(array $tokens, int $index, int $count): ?string
    {
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            return null;
        }

        return null;
    }

    /**
     * Whether the T_CLASS token belongs to "::class" or an anonymous class
     * instead of a class declaration.
     *
     * @param  list<mixed>  $tokens
     */
    private function isNonDeclarationClassToken(array $tokens, int $index): bool
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) && in_array($token[0], [T_DOUBLE_COLON, T_NEW], true);
        }

        return false;
    }
}
