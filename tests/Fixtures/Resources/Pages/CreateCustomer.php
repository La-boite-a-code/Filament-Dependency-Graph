<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\CustomerResource;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
