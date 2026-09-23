<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LaBoiteACode\DependencyGraph\Discovery\Support\MethodSource;
use LaBoiteACode\DependencyGraph\Discovery\Support\SourceScanner;
use LaBoiteACode\DependencyGraph\Domain\DTO\Http\DispatchReference;
use LaBoiteACode\DependencyGraph\Domain\Enums\DispatchKind;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Throwable;

/**
 * Reads one method without executing it: the models it references
 * statically and what it dispatches, optionally following the application
 * classes it calls into, one level deep.
 */
final class MethodInspector
{
    public function __construct(
        private readonly SourceScanner $scanner,
        private readonly DispatchClassifier $classifier,
    ) {}

    /**
     * @param  array<string, true>  $eventClasses  Events known to the dispatcher.
     */
    public function inspect(ReflectionMethod $method, DiscoveryContext $context, array $eventClasses): MethodFindings
    {
        $source = $this->scanner->method($method);

        if ($source === null) {
            return new MethodFindings([], [], false);
        }

        $models = $this->models($source);
        $dispatches = $this->dispatches($source, $method, $context, $eventClasses, []);

        if ($context->followInjectedClasses) {
            foreach ($this->collaborators($source, $method, $context) as [$collaborator, $collaboratorMethod]) {
                $followed = $this->scanner->method($collaboratorMethod);

                if ($followed === null) {
                    continue;
                }

                $models = [...$models, ...$this->models($followed)];
                $dispatches = [
                    ...$dispatches,
                    ...$this->dispatches($followed, $collaboratorMethod, $context, $eventClasses, [$collaborator]),
                ];
            }
        }

        $models = array_values(array_unique($models));
        sort($models, SORT_STRING);

        $views = [];

        foreach ($source->viewLiterals() as $literal) {
            if ($literal['how'] === 'view') {
                $views[$literal['name']] = true;
            }
        }

        $views = array_keys($views);
        sort($views, SORT_STRING);

        return new MethodFindings($models, $this->uniqueDispatches($dispatches), true, $views);
    }

    /**
     * @return list<string>
     */
    private function models(MethodSource $source): array
    {
        $models = [];

        foreach ($source->staticClassReferences() as $reference) {
            if ($this->isSubclassOf($reference['class'], Model::class)) {
                $models[] = $reference['class'];
            }
        }

        return $models;
    }

    /**
     * @param  array<string, true>  $eventClasses
     * @param  list<string>  $via
     * @return list<DispatchReference>
     */
    private function dispatches(
        MethodSource $source,
        ReflectionMethod $method,
        DiscoveryContext $context,
        array $eventClasses,
        array $via,
    ): array {
        $file = $method->getFileName();
        $path = is_string($file) ? PackagePath::relative($file, $context->basePath) : '';
        $dispatches = [];

        foreach ($source->staticCalls() as $call) {
            if (! $this->classifier->isDispatchMethod($call['method'])) {
                continue;
            }

            $kind = $this->classifier->classify($call['class'], $eventClasses);

            if ($kind === DispatchKind::Job || $kind === DispatchKind::Event) {
                $dispatches[] = new DispatchReference($call['class'], $kind, $via, $path . ':' . $call['line']);
            }
        }

        foreach ($source->instantiations() as $instantiation) {
            $kind = $this->classifier->classify($instantiation['class'], $eventClasses);

            if ($kind !== null) {
                $dispatches[] = new DispatchReference(
                    $instantiation['class'],
                    $kind,
                    $via,
                    $path . ':' . $instantiation['line'],
                );
            }
        }

        return $dispatches;
    }

    /**
     * Application classes reached through typed parameters or properties,
     * paired with the method called on them.
     *
     * @return list<array{0: string, 1: ReflectionMethod}>
     */
    private function collaborators(MethodSource $source, ReflectionMethod $method, DiscoveryContext $context): array
    {
        $parameterTypes = [];

        foreach ($method->getParameters() as $parameter) {
            $parameterTypes[$parameter->getName()] = $this->className($parameter->getType());
        }

        $class = $method->getDeclaringClass();
        $collaborators = [];

        foreach ($source->collaboratorCalls() as $call) {
            $type = $call['variable'] !== null
                ? ($parameterTypes[$call['variable']] ?? null)
                : $this->propertyType($class, (string) $call['property']);

            if ($type === null || ! $this->isFollowable($type, $class->getName(), $context)) {
                continue;
            }

            try {
                $target = new ReflectionMethod($type, $call['method']);
            } catch (Throwable) {
                continue;
            }

            if ($target->isAbstract() || $target->getDeclaringClass()->isInternal()) {
                continue;
            }

            $collaborators[$type . '::' . $target->getName()] = [$type, $target];
        }

        ksort($collaborators, SORT_STRING);

        return array_values($collaborators);
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private function propertyType(ReflectionClass $class, string $property): ?string
    {
        if (! $class->hasProperty($property)) {
            return null;
        }

        return $this->className($class->getProperty($property)->getType());
    }

    private function className(?ReflectionType $type): ?string
    {
        return $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;
    }

    private function isFollowable(string $class, string $caller, DiscoveryContext $context): bool
    {
        return $class !== $caller
            && NamespaceMatcher::matchesNamespace($class, $context->httpApplicationNamespaces)
            && ! NamespaceMatcher::matchesNamespace($class, $context->httpControllerNamespaces)
            && ! $this->isSubclassOf($class, Model::class)
            && ! $this->isSubclassOf($class, FormRequest::class);
    }

    private function isSubclassOf(string $class, string $parent): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, $parent);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<DispatchReference>  $dispatches
     * @return list<DispatchReference>
     */
    private function uniqueDispatches(array $dispatches): array
    {
        $unique = [];

        foreach ($dispatches as $dispatch) {
            $key = $dispatch->kind->value . '|' . $dispatch->class . '|' . implode(',', $dispatch->via);
            $unique[$key] ??= $dispatch;
        }

        ksort($unique, SORT_STRING);

        return array_values($unique);
    }
}
