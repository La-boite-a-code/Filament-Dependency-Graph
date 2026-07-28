<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire;

use Livewire\Component;

final class StandaloneCounter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}
