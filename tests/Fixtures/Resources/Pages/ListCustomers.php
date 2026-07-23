<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\CustomerResource;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
