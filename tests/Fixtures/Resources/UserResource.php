<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\User;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages\ListUsers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
