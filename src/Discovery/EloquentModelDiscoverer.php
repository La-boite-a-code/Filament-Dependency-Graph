<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LaBoiteACode\DependencyGraph\Contracts\ModelDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\ClassName;
use LaBoiteACode\DependencyGraph\Support\NamespaceMatcher;
use LaBoiteACode\DependencyGraph\Support\PackagePath;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use Throwable;

use function class_uses_recursive;

final class EloquentModelDiscoverer implements CollectsDiscoveryWarnings, ModelDiscoverer
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly ClassCandidateFinder $candidates,
        private readonly ModelInstantiator $instantiator,
    ) {}

    public function discover(DiscoveryContext $context): array
    {
        $classes = $this->candidates->fromPaths($context->modelPaths);

        if ($context->vendorModelsEnabled && $context->vendorModelNamespaces !== []) {
            $classes = [
                ...$classes,
                ...$this->candidates->fromComposerNamespaces(
                    $context->vendorModelNamespaces,
                    $context->vendorPath,
                ),
            ];
        }

        $models = [];

        foreach ($classes as $class) {
            $model = $this->discoverClass($class, $context);

            if ($model !== null && ! isset($models[$model->id])) {
                $models[$model->id] = $model;
            }
        }

        ksort($models, SORT_STRING);

        return array_values($models);
    }

    /**
     * Discovers a single class, returning null when the class is not a
     * concrete Eloquent model or is excluded by configuration.
     */
    public function discoverClass(string $class, DiscoveryContext $context): ?ModelData
    {
        $class = ClassName::normalize($class);

        try {
            if (! class_exists($class)) {
                return null;
            }
        } catch (Throwable $exception) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'class_not_loadable',
                message: sprintf('Class [%s] could not be loaded: %s', $class, $exception->getMessage()),
                class: $class,
                exceptionClass: $exception::class,
            );

            return null;
        }

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        if (NamespaceMatcher::matchesClass($class, $context->excludedClasses)) {
            return null;
        }

        if (NamespaceMatcher::matchesNamespace($class, $context->excludedNamespaces)) {
            return null;
        }

        $file = $reflection->getFileName();
        $applicationOwned = is_string($file)
            && PackagePath::isApplicationOwned($file, $context->basePath, $context->vendorPath);

        try {
            $instance = $this->instantiator->instantiate($class);
        } catch (Throwable $exception) {
            return $this->partialModel($class, $applicationOwned, $exception);
        }

        try {
            $shortName = ClassName::shortName($class);
            $table = $this->nonEmptyString(
                $instance->getTable(),
                Str::snake(Str::pluralStudly($shortName)),
            );

            if (in_array($table, $context->excludedTables, true)) {
                return null;
            }

            $traits = array_values(class_uses_recursive($class));
            sort($traits, SORT_STRING);

            $casts = $instance->getCasts();
            ksort($casts, SORT_STRING);

            return new ModelData(
                id: StableIdentifier::model($class),
                class: $class,
                shortName: $shortName,
                namespace: ClassName::namespace($class),
                table: $table,
                connection: $this->nonEmptyString($instance->getConnectionName(), 'default'),
                primaryKey: $this->nullableString($instance->getKeyName()),
                keyType: $this->nonEmptyString($instance->getKeyType(), 'int'),
                incrementing: (bool) $instance->getIncrementing(),
                timestamps: $instance->usesTimestamps(),
                softDeletes: in_array(SoftDeletes::class, $traits, true),
                traits: $traits,
                casts: array_map(static fn (mixed $cast): string => (string) $cast, $casts),
                fillable: array_values($instance->getFillable()),
                guarded: array_values($instance->getGuarded()),
                hidden: array_values($instance->getHidden()),
                visible: array_values($instance->getVisible()),
                status: DiscoveryStatus::Complete,
                warnings: [],
                applicationOwned: $applicationOwned,
            );
        } catch (Throwable $exception) {
            return $this->partialModel($class, $applicationOwned, $exception);
        }
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    private function nonEmptyString(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Builds model data from static information only, used when the model
     * cannot be instantiated. Eloquent defaults are assumed and the record
     * is flagged as partial.
     */
    private function partialModel(string $class, bool $applicationOwned, Throwable $exception): ModelData
    {
        $shortName = ClassName::shortName($class);

        $traits = array_values(class_uses_recursive($class));
        sort($traits, SORT_STRING);

        return new ModelData(
            id: StableIdentifier::model($class),
            class: $class,
            shortName: $shortName,
            namespace: ClassName::namespace($class),
            table: Str::snake(Str::pluralStudly($shortName)),
            connection: 'default',
            primaryKey: 'id',
            keyType: 'int',
            incrementing: true,
            timestamps: true,
            softDeletes: in_array(SoftDeletes::class, $traits, true),
            traits: $traits,
            casts: [],
            fillable: [],
            guarded: ['*'],
            hidden: [],
            visible: [],
            status: DiscoveryStatus::Partial,
            warnings: [
                sprintf('Model could not be instantiated: %s', $exception->getMessage()),
            ],
            applicationOwned: $applicationOwned,
        );
    }
}
