<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain\Enums;

enum RelationType: string
{
    case BelongsTo = 'belongs_to';
    case HasOne = 'has_one';
    case HasMany = 'has_many';
    case BelongsToMany = 'belongs_to_many';
    case HasOneThrough = 'has_one_through';
    case HasManyThrough = 'has_many_through';
    case MorphTo = 'morph_to';
    case MorphOne = 'morph_one';
    case MorphMany = 'morph_many';
    case MorphToMany = 'morph_to_many';
    case MorphedByMany = 'morphed_by_many';

    public function isPolymorphic(): bool
    {
        return match ($this) {
            self::MorphTo,
            self::MorphOne,
            self::MorphMany,
            self::MorphToMany,
            self::MorphedByMany => true,
            default => false,
        };
    }

    public function usesPivotTable(): bool
    {
        return match ($this) {
            self::BelongsToMany,
            self::MorphToMany,
            self::MorphedByMany => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BelongsTo => 'belongsTo',
            self::HasOne => 'hasOne',
            self::HasMany => 'hasMany',
            self::BelongsToMany => 'belongsToMany',
            self::HasOneThrough => 'hasOneThrough',
            self::HasManyThrough => 'hasManyThrough',
            self::MorphTo => 'morphTo',
            self::MorphOne => 'morphOne',
            self::MorphMany => 'morphMany',
            self::MorphToMany => 'morphToMany',
            self::MorphedByMany => 'morphedByMany',
        };
    }
}
