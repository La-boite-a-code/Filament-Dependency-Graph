<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaBoiteACode\DependencyGraph\Contracts\RelationDiscoverer;
use LaBoiteACode\DependencyGraph\Discovery\Support\CollectsDiscoveryWarnings;
use LaBoiteACode\DependencyGraph\Discovery\Support\DocblockRelationParser;
use LaBoiteACode\DependencyGraph\Discovery\Support\RelationTypeMap;
use LaBoiteACode\DependencyGraph\Discovery\Support\SchemaInspector;
use LaBoiteACode\DependencyGraph\Domain\DTO\ModelData;
use LaBoiteACode\DependencyGraph\Domain\DTO\RelationData;
use LaBoiteACode\DependencyGraph\Domain\Enums\DiscoveryStatus;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryContext;
use LaBoiteACode\DependencyGraph\Domain\ValueObjects\DiscoveryWarning;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final class EloquentRelationDiscoverer implements CollectsDiscoveryWarnings, RelationDiscoverer
{
    /** @var list<DiscoveryWarning> */
    private array $warnings = [];

    public function __construct(
        private readonly ModelInstantiator $instantiator,
        private readonly SchemaInspector $schema,
    ) {}

    public function discover(ModelData $model, DiscoveryContext $context): array
    {
        if (! $context->discoverRelations) {
            return [];
        }

        try {
            if (! class_exists($model->class)) {
                return [];
            }

            $instance = $this->instantiator->instantiate($model->class);
        } catch (Throwable) {
            return [];
        }

        $relations = [];

        foreach ((new ReflectionClass($model->class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $relation = $this->discoverMethod($model, $instance, $method, $context);

            if ($relation !== null && ! isset($relations[$relation->id])) {
                $relations[$relation->id] = $relation;
            }
        }

        ksort($relations, SORT_STRING);

        return array_values($relations);
    }

    public function pullWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    private function discoverMethod(
        ModelData $model,
        Model $instance,
        ReflectionMethod $method,
        DiscoveryContext $context,
    ): ?RelationData {
        if (! $this->isCandidate($model, $method, $context)) {
            return null;
        }

        $name = $method->getName();

        $declaredRelationClass = $this->declaredRelationClass($method);
        $docblock = null;

        if ($declaredRelationClass === null) {
            if ($method->getReturnType() !== null) {
                return null;
            }

            if ($context->useDocblocks) {
                $docblock = DocblockRelationParser::parse($method);
            }

            if ($docblock === null && ! $context->useHeuristicInvocation) {
                return null;
            }
        }

        try {
            $result = $method->invoke($instance);
        } catch (Throwable $exception) {
            if ($declaredRelationClass === null && $docblock === null) {
                return null;
            }

            return $this->partialRelation($model, $name, $declaredRelationClass, $docblock, $exception);
        }

        if (! $result instanceof Relation) {
            return null;
        }

        $type = RelationTypeMap::fromRelation($result);

        if ($type === null) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'unsupported_relation',
                message: sprintf(
                    'Relation [%s::%s] uses unsupported relation class [%s].',
                    $model->class,
                    $name,
                    $result::class,
                ),
                class: $model->class,
                method: $name,
            );

            return null;
        }

        return $this->relationFromInstance($model, $name, $type, $result, $context);
    }

    private function isCandidate(ModelData $model, ReflectionMethod $method, DiscoveryContext $context): bool
    {
        if ($method->isStatic() || $method->isAbstract()) {
            return false;
        }

        if ($method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        $name = $method->getName();

        if (str_starts_with($name, '__')) {
            return false;
        }

        if (str_starts_with($name, 'scope')) {
            return false;
        }

        if (preg_match('/^(get|set)[A-Z].*Attribute$/', $name) === 1) {
            return false;
        }

        if (str_starts_with($method->getDeclaringClass()->getName(), 'Illuminate\\')) {
            return false;
        }

        foreach ($context->excludedRelations as $excluded) {
            if (strcasecmp($excluded, $model->class . '::' . $name) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the native return type when it is a known relation class.
     */
    private function declaredRelationClass(ReflectionMethod $method): ?string
    {
        $returnType = $method->getReturnType();

        if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return null;
        }

        $class = $returnType->getName();

        if ($class === Attribute::class) {
            return null;
        }

        return RelationTypeMap::isRelationClass($class) ? $class : null;
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private function relationFromInstance(
        ModelData $model,
        string $method,
        RelationType $type,
        Relation $relation,
        DiscoveryContext $context,
    ): RelationData {
        $foreignKey = null;
        $ownerKey = null;
        $localKey = null;
        $pivotTable = null;
        $morphType = null;
        $warnings = [];

        $relatedClass = $type === RelationType::MorphTo ? null : $relation->getRelated()::class;

        if ($relation instanceof MorphTo) {
            $foreignKey = $relation->getForeignKeyName();
            $morphType = $relation->getMorphType();
        } elseif ($relation instanceof MorphToMany) {
            $foreignKey = $relation->getForeignPivotKeyName();
            $ownerKey = $relation->getRelatedPivotKeyName();
            $localKey = $relation->getParentKeyName();
            $pivotTable = $relation->getTable();
            $morphType = $relation->getMorphType();
        } elseif ($relation instanceof BelongsToMany) {
            $foreignKey = $relation->getForeignPivotKeyName();
            $ownerKey = $relation->getRelatedPivotKeyName();
            $localKey = $relation->getParentKeyName();
            $pivotTable = $relation->getTable();
        } elseif ($relation instanceof MorphOneOrMany) {
            $foreignKey = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();
            $morphType = $relation->getMorphType();
        } elseif ($relation instanceof HasOneOrMany) {
            $foreignKey = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();
        } elseif ($relation instanceof HasOneThrough || $relation instanceof HasManyThrough) {
            $foreignKey = $relation->getFirstKeyName();
            $ownerKey = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();
        } elseif ($relation instanceof BelongsTo) {
            $foreignKey = $relation->getForeignKeyName();
            $ownerKey = $relation->getOwnerKeyName();
        }

        $nullable = null;

        if (
            $context->inspectDatabaseSchema
            && $foreignKey !== null
            && ($type === RelationType::BelongsTo || $type === RelationType::MorphTo)
        ) {
            if ($this->schema->isAvailable($model->connection, $model->table)) {
                $nullable = $this->schema->isColumnNullable($model->connection, $model->table, $foreignKey);
            } else {
                $warnings[] = sprintf(
                    'Schema metadata is unavailable for table [%s], nullability was not verified.',
                    $model->table,
                );
            }
        }

        if ($type === RelationType::MorphTo) {
            $warnings[] = 'Polymorphic target cannot be resolved to a single model.';
        }

        return new RelationData(
            id: StableIdentifier::relation($model->class, $method),
            sourceModelId: $model->id,
            targetModelId: $relatedClass === null ? null : StableIdentifier::model($relatedClass),
            method: $method,
            type: $type,
            relatedClass: $relatedClass,
            foreignKey: $foreignKey,
            ownerKey: $ownerKey,
            localKey: $localKey,
            pivotTable: $pivotTable,
            morphType: $morphType,
            nullable: $nullable,
            polymorphic: $type->isPolymorphic(),
            inverseDiscovered: false,
            status: DiscoveryStatus::Complete,
            warnings: $warnings,
        );
    }

    /**
     * Builds partial relation data when a declared relation method threw
     * during discovery. The relation type falls back to the declared return
     * type or the docblock.
     *
     * @param  array{type: RelationType, target: string|null}|null  $docblock
     */
    private function partialRelation(
        ModelData $model,
        string $method,
        ?string $declaredRelationClass,
        ?array $docblock,
        Throwable $exception,
    ): ?RelationData {
        $type = $declaredRelationClass !== null
            ? RelationTypeMap::fromName($declaredRelationClass)
            : ($docblock['type'] ?? null);

        if ($type === null) {
            $this->warnings[] = new DiscoveryWarning(
                type: 'relation_discovery_failed',
                message: sprintf(
                    'Relation [%s::%s] threw during discovery and its type could not be resolved: %s',
                    $model->class,
                    $method,
                    $exception->getMessage(),
                ),
                class: $model->class,
                method: $method,
                exceptionClass: $exception::class,
            );

            return null;
        }

        $target = $docblock['target'] ?? null;

        return new RelationData(
            id: StableIdentifier::relation($model->class, $method),
            sourceModelId: $model->id,
            targetModelId: $target === null ? null : StableIdentifier::model($target),
            method: $method,
            type: $type,
            relatedClass: $target,
            foreignKey: null,
            ownerKey: null,
            localKey: null,
            pivotTable: null,
            morphType: null,
            nullable: null,
            polymorphic: $type->isPolymorphic(),
            inverseDiscovered: false,
            status: DiscoveryStatus::Partial,
            warnings: [
                sprintf('Relation method threw during discovery: %s', $exception->getMessage()),
            ],
        );
    }
}
