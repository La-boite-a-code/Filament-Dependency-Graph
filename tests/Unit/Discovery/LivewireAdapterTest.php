<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Discovery\Views\LivewireAdapter;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\StandaloneCounter;

it('resolves Livewire 4 classes, single-file and multi-file components', function (): void {
    if (! app()->bound('livewire.finder')) {
        $this->markTestSkipped('The component finder needs Livewire 4.');
    }

    $path = dirname(__DIR__, 2) . '/Fixtures/views-livewire4/livewire';
    app('livewire.finder')->addLocation(viewPath: $path);
    $adapter = app(LivewireAdapter::class);
    $file = static fn (string $name): string|false => realpath((string) ($adapter->resolve($name)['value'] ?? ''));

    expect($adapter->resolve('standalone-counter'))->toBe(['type' => 'class', 'value' => StandaloneCounter::class])
        ->and($adapter->resolve('\\' . StandaloneCounter::class))->toBe(['type' => 'class', 'value' => StandaloneCounter::class])
        ->and($adapter->resolve('counter')['type'] ?? null)->toBe('file')
        ->and($file('counter'))->toBe(realpath($path . '/⚡counter.blade.php'))
        ->and($file('panel'))->toBe(realpath($path . '/⚡panel/panel.blade.php'))
        ->and($adapter->resolve('does-not-exist'))->toBeNull();
});

it('resolves Livewire 3 aliases without calling missing-component resolvers', function (): void {
    if (app()->bound('livewire.finder')) {
        $this->markTestSkipped('The component registry is Livewire 3 only.');
    }

    $adapter = app(LivewireAdapter::class);

    expect($adapter->resolve('standalone-counter'))->toBe(['type' => 'class', 'value' => StandaloneCounter::class])
        ->and($adapter->resolve('does-not-exist'))->toBeNull();
});
