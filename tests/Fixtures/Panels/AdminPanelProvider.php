<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels;

use Filament\Panel;
use Filament\PanelProvider;
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\CustomerResource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\OrderResource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\ProductResource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\UserResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([
                CustomerResource::class,
                OrderResource::class,
                ProductResource::class,
                UserResource::class,
            ])
            ->plugin(DependencyGraphPlugin::make());
    }
}
