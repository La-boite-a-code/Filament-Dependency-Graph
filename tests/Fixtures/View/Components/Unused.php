<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components;

use Illuminate\View\Component;

/**
 * Never used in a template: discovered through the component paths.
 */
final class Unused extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}
