<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\CustomerResource;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
