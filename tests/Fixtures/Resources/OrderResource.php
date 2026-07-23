<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Order;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages\ListOrders;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\RelationManagers\ItemsRelationManager;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Shop';
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
