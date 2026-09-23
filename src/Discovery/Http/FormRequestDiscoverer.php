<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\FormRequestData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Describes form requests. Their rules are only read on request: the form
 * request is created without the container, which would validate it, and
 * rules() runs outside of any HTTP request.
 */
final class FormRequestDiscoverer
{
    /**
     * @param  list<string>  $classes
     * @return list<FormRequestData>
     */
    public function discover(array $classes, DiscoveryContext $context): array
    {
        $requests = [];

        foreach (array_unique($classes) as $class) {
            $request = $this->discoverClass($class, $context);
            $requests[$request->id] = $request;
        }

        ksort($requests, SORT_STRING);

        return array_values($requests);
    }

    private function discoverClass(string $class, DiscoveryContext $context): FormRequestData
    {
        try {
            if (! class_exists($class)) {
                throw new RuntimeException('class not found');
            }

            $reflection = new ReflectionClass($class);
        } catch (Throwable $exception) {
            return new FormRequestData(
                id: StableIdentifier::formRequest($class),
                class: $class,
                file: null,
                hasAuthorize: false,
                hasRules: false,
                rules: null,
                status: DiscoveryStatus::Failed,
                warnings: [sprintf('Form request could not be loaded: %s', $exception->getMessage())],
            );
        }

        $path = $reflection->getFileName();
        $hasRules = $reflection->hasMethod('rules');
        $warnings = [];
        $rules = null;

        if ($context->invokeFormRequestRules && $hasRules) {
            try {
                $rules = $this->rules($reflection);
            } catch (Throwable $exception) {
                $warnings[] = sprintf('rules() could not be read outside of a request: %s', $exception->getMessage());
            }
        }

        return new FormRequestData(
            id: StableIdentifier::formRequest($class),
            class: $class,
            file: is_string($path) ? PackagePath::relative($path, $context->basePath) : null,
            hasAuthorize: $reflection->hasMethod('authorize'),
            hasRules: $hasRules,
            rules: $rules,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<string, list<string>>
     */
    private function rules(ReflectionClass $reflection): array
    {
        if ($reflection->getMethod('rules')->getNumberOfRequiredParameters() > 0) {
            throw new RuntimeException('rules() expects injected arguments.');
        }

        $request = $reflection->newInstance();

        if ($request instanceof FormRequest) {
            $request->setContainer(Container::getInstance());
        }

        $raw = $reflection->getMethod('rules')->invoke($request);
        $rules = [];

        foreach (is_array($raw) ? $raw : [] as $field => $fieldRules) {
            $rules[(string) $field] = $this->normalize($fieldRules);
        }

        ksort($rules, SORT_STRING);

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function normalize(mixed $rules): array
    {
        if (is_string($rules)) {
            return array_values(array_filter(explode('|', $rules), static fn (string $rule): bool => $rule !== ''));
        }

        if (! is_array($rules)) {
            $rules = [$rules];
        }

        $normalized = [];

        foreach ($rules as $rule) {
            $normalized[] = match (true) {
                $rule instanceof Closure => 'closure',
                $rule instanceof Stringable => (string) $rule,
                is_object($rule) => $rule::class,
                is_array($rule) => implode(',', array_map(static fn (mixed $part): string => is_scalar($part) ? (string) $part : get_debug_type($part), $rule)),
                is_scalar($rule) => (string) $rule,
                default => get_debug_type($rule),
            };
        }

        return $normalized;
    }
}
