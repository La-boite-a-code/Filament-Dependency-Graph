<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\FilamentViews\Widgets;

use Filament\Widgets\Widget;

final class StatsWidget extends Widget
{
    /** Counts instantiations: discovery must never create a widget. */
    public static int $instances = 0;

    protected string $view = 'filament.widgets.stats';

    public function __construct()
    {
        self::$instances++;
    }
}
