<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LaBoiteACode\DependencyGraph\Contracts\LivewireComponentDiscoverer as LivewireComponentDiscovererContract;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\LivewireComponentData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use Livewire\Component;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * Discovers application-owned Livewire components without instantiating them.
 *
 * Model dependencies come from declared property/method types and explicit
 * static model references in the component source (for example
 * Order::query()). This keeps discovery read-only while covering the common
 * Livewire patterns used by route-bound properties and component actions.
 */
final class LivewireComponentDiscoverer implements CollectsDiscoveryWarnings, LivewireComponentDiscovererContract
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly ClassCandidateFinder $candidates,
    ) {}

    public function discover(DiscoveryContext $context): array
    {
        $this->warnings = [];

        if (! $context->discoverLivewireComponents) {
            return [];
        }

        $components = [];

        foreach ($this->candidates->fromPaths($context->livewirePaths) as $class) {
            $component = $this->discoverClass($class, $context);

            if ($component !== null) {
                $components[$component->id] = $component;
            }
        }

        ksort($components, SORT_STRING);

        return array_values($components);
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    private function discoverClass(string $class, DiscoveryContext $context): ?LivewireComponentData
    {
        $class = ClassName::normalize($class);

        try {
            if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
                return null;
            }
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'livewire_component_not_loadable',
                message: sprintf('Livewire component [%s] could not be loaded: %s', $class, $exception->getMessage()),
                class: $class,
                exceptionClass: $exception::class,
            );

            return null;
        }

        if (
            NamespaceMatcher::matchesClass($class, $context->excludedClasses)
            || NamespaceMatcher::matchesNamespace($class, $context->excludedNamespaces)
        ) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        $properties = $this->publicProperties($reflection);
        $methods = $this->publicMethods($reflection);
        $modelReferences = $this->typedModelReferences($reflection);
        $warnings = [];

        $file = $reflection->getFileName();
        $source = is_string($file) ? @file_get_contents($file) : false;

        if ($source === false) {
            $warnings[] = 'Source file could not be read; static model references and the rendered view were not inspected.';
            $view = null;
        } else {
            $modelReferences = $this->mergeReferences(
                $modelReferences,
                $this->sourceModelReferences($reflection, $source),
            );
            $view = $this->renderedView($source);
        }

        ksort($modelReferences, SORT_STRING);

        foreach ($modelReferences as &$locations) {
            $locations = array_values(array_unique($locations));
            sort($locations, SORT_STRING);
        }
        unset($locations);

        return new LivewireComponentData(
            id: StableIdentifier::livewireComponent($class),
            class: $class,
            shortName: ClassName::shortName($class),
            namespace: ClassName::namespace($class),
            alias: $this->componentAlias($class, $context->livewireNamespaces),
            view: $view,
            file: is_string($file) ? $this->relativePath($file, $context->basePath) : null,
            publicProperties: $properties,
            publicMethods: $methods,
            modelReferences: $modelReferences,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * @param  ReflectionClass<Component>  $reflection
     * @return list<string>
     */
    private function publicProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName() || $property->isStatic()) {
                continue;
            }

            $properties[] = $property->getName();
        }

        sort($properties, SORT_STRING);

        return $properties;
    }

    /**
     * @param  ReflectionClass<Component>  $reflection
     * @return list<string>
     */
    private function publicMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            if ($method->isConstructor() || $method->isDestructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods, SORT_STRING);

        return $methods;
    }

    /**
     * @param  ReflectionClass<Component>  $reflection
     * @return array<string, list<string>>
     */
    private function typedModelReferences(ReflectionClass $reflection): array
    {
        $references = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            foreach ($this->typeNames($property->getType()) as $type) {
                $this->recordModelReference($references, $type, 'property:' . $property->getName());
            }
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                foreach ($this->typeNames($parameter->getType()) as $type) {
                    $this->recordModelReference(
                        $references,
                        $type,
                        sprintf('parameter:%s.%s', $method->getName(), $parameter->getName()),
                    );
                }
            }

            foreach ($this->typeNames($method->getReturnType()) as $type) {
                $this->recordModelReference($references, $type, 'return:' . $method->getName());
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function typeNames(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? [] : [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];

            foreach ($type->getTypes() as $member) {
                $names = [...$names, ...$this->typeNames($member)];
            }

            return $names;
        }

        return [];
    }

    /**
     * @param  ReflectionClass<Component>  $reflection
     * @return array<string, list<string>>
     */
    private function sourceModelReferences(ReflectionClass $reflection, string $source): array
    {
        $references = [];
        $imports = $this->importsBeforeClass($reflection, $source);

        foreach ($this->staticClassReferences($source) as $reference) {
            $class = $this->resolveSourceClass($reference, $reflection->getNamespaceName(), $imports);

            if ($class !== null) {
                $this->recordModelReference(
                    $references,
                    $class,
                    'source:' . ClassName::shortName($class),
                );
            }
        }

        return $references;
    }

    /**
     * Reads actual class tokens followed by ::, ignoring comments and string
     * literals that merely contain code-like text.
     *
     * @return list<string>
     */
    private function staticClassReferences(string $source): array
    {
        $tokens = token_get_all($source);
        $references = [];
        $classTokenTypes = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || ! in_array($token[0], $classTokenTypes, true)) {
                continue;
            }

            $next = $this->nextSignificantToken($tokens, $index + 1);

            if (is_array($next) && $next[0] === T_DOUBLE_COLON) {
                $references[] = $token[1];
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * @param  ReflectionClass<Component>  $reflection
     * @return array<string, string> Import alias to fully qualified class.
     */
    private function importsBeforeClass(ReflectionClass $reflection, string $source): array
    {
        $lines = preg_split('/\R/', $source) ?: [];
        $prefix = implode("\n", array_slice($lines, 0, max(0, $reflection->getStartLine() - 1)));
        $imports = [];

        preg_match_all('/^\s*use\s+([^;]+);/m', $prefix, $matches);

        foreach ($matches[1] as $statement) {
            $statement = trim($statement);

            if (
                $statement === ''
                || str_starts_with($statement, 'function ')
                || str_starts_with($statement, 'const ')
            ) {
                continue;
            }

            foreach ($this->expandImportStatement($statement) as $import) {
                $parts = preg_split('/\s+as\s+/i', trim($import), 2) ?: [];
                $class = ltrim($parts[0] ?? '', '\\');

                if ($class === '') {
                    continue;
                }

                $alias = $parts[1] ?? ClassName::shortName($class);
                $imports[$alias] = $class;
            }
        }

        return $imports;
    }

    /**
     * @return list<string>
     */
    private function expandImportStatement(string $statement): array
    {
        if (preg_match('/^(.+?)\\\\\{(.+)}$/', $statement, $matches) === 1) {
            $prefix = rtrim($matches[1], '\\');

            return array_map(
                static fn (string $member): string => $prefix . '\\' . trim($member),
                explode(',', $matches[2]),
            );
        }

        return array_map('trim', explode(',', $statement));
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function resolveSourceClass(string $reference, string $namespace, array $imports): ?string
    {
        $reference = trim($reference);

        if (in_array(strtolower($reference), ['self', 'static', 'parent'], true)) {
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
     * @param  array<string, list<string>>  $references
     */
    private function recordModelReference(array &$references, string $class, string $location): void
    {
        try {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $references[$class][] = $location;
    }

    /**
     * @param  array<string, list<string>>  $left
     * @param  array<string, list<string>>  $right
     * @return array<string, list<string>>
     */
    private function mergeReferences(array $left, array $right): array
    {
        foreach ($right as $modelId => $locations) {
            $left[$modelId] = [...($left[$modelId] ?? []), ...$locations];
        }

        return $left;
    }

    private function renderedView(string $source): ?string
    {
        $tokens = token_get_all($source);

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'view') {
                continue;
            }

            $openingParenthesisIndex = $this->nextSignificantTokenIndex($tokens, $index + 1);

            if ($openingParenthesisIndex === null || $tokens[$openingParenthesisIndex] !== '(') {
                continue;
            }

            $argument = $this->nextSignificantToken($tokens, $openingParenthesisIndex + 1);

            if (! is_array($argument) || $argument[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            return stripcslashes(substr($argument[1], 1, -1));
        }

        return null;
    }

    /**
     * @param  list<mixed>  $tokens
     */
    private function nextSignificantToken(array $tokens, int $offset): mixed
    {
        $index = $this->nextSignificantTokenIndex($tokens, $offset);

        return $index === null ? null : $tokens[$index];
    }

    /**
     * @param  list<mixed>  $tokens
     */
    private function nextSignificantTokenIndex(array $tokens, int $offset): ?int
    {
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param  list<string>  $namespaces
     */
    private function componentAlias(string $class, array $namespaces): string
    {
        usort($namespaces, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($namespaces as $namespace) {
            $prefix = rtrim(ClassName::normalize($namespace), '\\') . '\\';

            if (! str_starts_with($class, $prefix)) {
                continue;
            }

            return implode('.', array_map(
                static fn (string $segment): string => Str::kebab($segment),
                explode('\\', substr($class, strlen($prefix))),
            ));
        }

        return Str::kebab(ClassName::shortName($class));
    }

    private function relativePath(string $file, string $basePath): string
    {
        $basePath = rtrim($basePath, '/\\');

        if ($basePath !== '' && str_starts_with($file, $basePath . DIRECTORY_SEPARATOR)) {
            return substr($file, strlen($basePath) + 1);
        }

        return basename($file);
    }
}
