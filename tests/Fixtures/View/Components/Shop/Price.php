<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Shop;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Price extends Component
{
    public function __construct(
        public float $amount = 0,
    ) {}

    public function render(): View
    {
        return view('components.shop.price');
    }
}
