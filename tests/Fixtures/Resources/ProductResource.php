<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Product;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages\ListProducts;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
        ];
    }
}
