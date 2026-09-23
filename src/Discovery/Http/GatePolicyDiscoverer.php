<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Illuminate\Contracts\Auth\Access\Gate;
use LaBoiteACode\DependencyGraph\Contracts\PolicyDiscoverer;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\PolicyData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Mirrors Gate::getPolicyFor() without resolving the policy: registered
 * policies, the UsePolicy attribute, then the gate's own name guessing,
 * which honours a custom guessPolicyNamesUsing() callback.
 */
final class GatePolicyDiscoverer implements PolicyDiscoverer
{
    private const USE_POLICY_ATTRIBUTE = 'Illuminate\\Database\\Eloquent\\Attributes\\UsePolicy';

    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function discover(array $modelClasses, DiscoveryContext $context): array
    {
        $registered = method_exists($this->gate, 'policies') ? $this->gate->policies() : [];
        $policies = [];

        foreach ($modelClasses as $modelClass) {
            $resolved = $this->resolve($modelClass, $registered);

            if ($resolved === null) {
                continue;
            }

            [$policyClass, $source] = $resolved;
            $policy = $this->describe($policyClass, $modelClass, $source, $context);
            $policies[$policy->id . '|' . $modelClass] = $policy;
        }

        ksort($policies, SORT_STRING);

        return array_values($policies);
    }

    /**
     * @param  array<mixed>  $registered
     * @return array{0: string, 1: string}|null
     */
    private function resolve(string $modelClass, array $registered): ?array
    {
        if (isset($registered[$modelClass]) && is_string($registered[$modelClass])) {
            return [ltrim($registered[$modelClass], '\\'), PolicyData::SOURCE_REGISTERED];
        }

        $attribute = $this->attributePolicy($modelClass);

        if ($attribute !== null) {
            return [$attribute, PolicyData::SOURCE_ATTRIBUTE];
        }

        foreach ($this->guessedPolicies($modelClass) as $guessed) {
            if (class_exists($guessed)) {
                return [ltrim($guessed, '\\'), PolicyData::SOURCE_CONVENTION];
            }
        }

        foreach ($registered as $expected => $policy) {
            if (is_string($expected) && is_string($policy) && is_subclass_of($modelClass, $expected)) {
                return [ltrim($policy, '\\'), PolicyData::SOURCE_REGISTERED];
            }
        }

        return null;
    }

    private function attributePolicy(string $modelClass): ?string
    {
        if (! class_exists(self::USE_POLICY_ATTRIBUTE) || ! class_exists($modelClass)) {
            return null;
        }

        $attributes = (new ReflectionClass($modelClass))->getAttributes(self::USE_POLICY_ATTRIBUTE);

        if ($attributes === []) {
            return null;
        }

        $arguments = $attributes[0]->getArguments();
        $policy = $arguments['class'] ?? $arguments[0] ?? null;

        return is_string($policy) ? ltrim($policy, '\\') : null;
    }

    /**
     * @return list<string>
     */
    private function guessedPolicies(string $modelClass): array
    {
        try {
            $method = new ReflectionMethod($this->gate, 'guessPolicyName');
            $guessed = $method->invoke($this->gate, $modelClass);
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter(is_array($guessed) ? $guessed : [$guessed], 'is_string'));
    }

    private function describe(string $policyClass, string $modelClass, string $source, DiscoveryContext $context): PolicyData
    {
        $abilities = [];
        $file = null;
        $warnings = [];

        try {
            if (! class_exists($policyClass)) {
                throw new RuntimeException('class not found');
            }

            $reflection = new ReflectionClass($policyClass);
            $path = $reflection->getFileName();
            $file = is_string($path) ? PackagePath::relative($path, $context->basePath) : null;

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();

                if (
                    $method->isStatic()
                    || str_starts_with($name, '__')
                    || in_array($name, ['before', 'after'], true)
                    || str_starts_with($method->getDeclaringClass()->getName(), 'Illuminate\\')
                ) {
                    continue;
                }

                $abilities[] = $name;
            }
        } catch (Throwable $exception) {
            $warnings[] = sprintf('Policy could not be loaded: %s', $exception->getMessage());
        }

        sort($abilities, SORT_STRING);

        return new PolicyData(
            id: StableIdentifier::policy($policyClass),
            class: $policyClass,
            file: $file,
            modelClass: $modelClass,
            abilities: $abilities,
            source: $source,
            status: $warnings === [] ? DiscoveryStatus::Complete : DiscoveryStatus::Partial,
            warnings: $warnings,
        );
    }
}
