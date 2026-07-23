<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels;

use Filament\Panel;
use Filament\PanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\BrokenResource;

class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('customer')
            ->path('portal')
            ->domain('customers.example.test')
            ->resources([
                BrokenResource::class,
            ]);
    }
}
