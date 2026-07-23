<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Models\Customer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages\CreateCustomer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages\EditCustomer;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages\ListCustomers;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Shop';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-users';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
