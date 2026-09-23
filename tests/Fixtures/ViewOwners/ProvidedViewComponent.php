<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\ViewOwners;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Livewire 4 renders the view returned by view() when there is no render();
 * on Livewire 3, view() is an ordinary method.
 */
final class ProvidedViewComponent extends Component
{
    public function view(): View
    {
        return view('partials.nav');
    }
}
