<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\ProductResource;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}
