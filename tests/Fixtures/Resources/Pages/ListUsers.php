<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
