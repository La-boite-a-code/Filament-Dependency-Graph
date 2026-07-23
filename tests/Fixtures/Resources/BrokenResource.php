<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\NotAModel;

class BrokenResource extends Resource
{
    protected static ?string $model = NotAModel::class;

    public static function getPages(): array
    {
        return [];
    }
}
