<?php

use Livewire\Component;

new class extends Component {
    public int $count = 0;
};
?>

<div>
    {{ $count }}
    <x-badge value="counter" />
</div>
