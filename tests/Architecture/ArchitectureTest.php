<?php

declare(strict_types=1);

arch('the domain layer does not depend on Filament or the presentation layer')
    ->expect('LaBoiteACode\DependencyGraph\Domain')
    ->not->toUse([
        'Filament',
        'Livewire',
        'LaBoiteACode\DependencyGraph\Filament',
        'LaBoiteACode\DependencyGraph\Discovery',
        'LaBoiteACode\DependencyGraph\Export',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
    ]);

arch('domain DTOs are final and readonly')
    ->expect('LaBoiteACode\DependencyGraph\Domain\DTO')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('domain value objects are final and readonly')
    ->expect('LaBoiteACode\DependencyGraph\Domain\ValueObjects')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('LaBoiteACode\DependencyGraph\Contracts')
    ->toBeInterfaces();

arch('no debugging functions remain')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('every source file uses strict types')
    ->expect('LaBoiteACode\DependencyGraph')
    ->toUseStrictTypes();

arch('the graph algorithms do not depend on the presentation layer')
    ->expect('LaBoiteACode\DependencyGraph\Graph')
    ->not->toUse([
        'Filament',
        'Livewire',
        'LaBoiteACode\DependencyGraph\Filament',
    ]);
