<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Alert extends Component
{
    /** Counts instantiations: discovery must never create a component. */
    public static int $instances = 0;

    public function __construct(
        public string $type = 'info',
    ) {
        self::$instances++;
    }

    public function render(): View
    {
        return view('components.alert');
    }
}
