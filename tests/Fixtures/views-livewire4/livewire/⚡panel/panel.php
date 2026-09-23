<?php

declare(strict_types=1);

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Panel'), Layout('pages.about')] class extends Component
{
    public bool $open = false;
};
