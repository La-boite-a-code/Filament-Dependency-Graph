<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Panels;

use Filament\Panel;
use Filament\PanelProvider;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\OrderResource;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Resources\UserResource;

class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operations')
            ->path('operations')
            ->resources([
                OrderResource::class,
                UserResource::class,
            ]);
    }
}
