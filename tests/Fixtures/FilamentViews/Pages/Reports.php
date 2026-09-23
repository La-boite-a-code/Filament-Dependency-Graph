<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\FilamentViews\Pages;

use Filament\Pages\Page;

/**
 * Declares its view through getView() rather than the property.
 */
final class Reports extends Page
{
    public function getView(): string
    {
        return 'pages.about';
    }
}
