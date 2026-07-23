<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaBoiteACode\DependencyGraph\Domain\Enums\RelationType;
use Throwable;

/**
 * Maps Eloquent relation implementations to domain relation types.
 *
 * The instanceof checks are ordered from the most specific subclass to the
 * most generic one, because several relation classes extend each other.
 */
final class RelationTypeMap
{
    private const SHORT_NAMES = [
        'BelongsTo' => RelationType::BelongsTo,
        'HasOne' => RelationType::HasOne,
        'HasMany' => RelationType::HasMany,
        'BelongsToMany' => RelationType::BelongsToMany,
        'HasOneThrough' => RelationType::HasOneThrough,
        'HasManyThrough' => RelationType::HasManyThrough,
        'MorphTo' => RelationType::MorphTo,
        'MorphOne' => RelationType::MorphOne,
        'MorphMany' => RelationType::MorphMany,
        'MorphToMany' => RelationType::MorphToMany,
    ];

    /**
     * @param  Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $relation
     */
    public static function fromRelation(Relation $relation): ?RelationType
    {
        return match (true) {
            $relation instanceof MorphTo => RelationType::MorphTo,
            $relation instanceof MorphToMany => $relation->getInverse()
                ? RelationType::MorphedByMany
                : RelationType::MorphToMany,
            $relation instanceof BelongsToMany => RelationType::BelongsToMany,
            $relation instanceof MorphMany => RelationType::MorphMany,
            $relation instanceof MorphOne => RelationType::MorphOne,
            $relation instanceof HasMany => RelationType::HasMany,
            $relation instanceof HasOne => RelationType::HasOne,
            $relation instanceof HasOneThrough => RelationType::HasOneThrough,
            $relation instanceof HasManyThrough => RelationType::HasManyThrough,
            $relation instanceof BelongsTo => RelationType::BelongsTo,
            default => null,
        };
    }

    /**
     * Maps a class name, or a docblock short name, to a relation type.
     * MorphedByMany cannot be detected from a name because Eloquent
     * implements it with the MorphToMany class.
     */
    public static function fromName(string $name): ?RelationType
    {
        $short = str_contains($name, '\\')
            ? substr($name, (int) strrpos($name, '\\') + 1)
            : $name;

        return self::SHORT_NAMES[$short] ?? null;
    }

    public static function isRelationClass(string $class): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, Relation::class);
        } catch (Throwable) {
            return false;
        }
    }
}
