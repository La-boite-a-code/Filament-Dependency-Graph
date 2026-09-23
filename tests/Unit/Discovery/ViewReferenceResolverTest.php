<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Discovery\Views\BladeTemplateScanner;
use LaBoiteACode\DependencyGraph\Discovery\Views\ViewReferenceResolver;
use LaBoiteACode\DependencyGraph\Discovery\Views\ViewResolution;
use LaBoiteACode\DependencyGraph\Support\StableIdentifier;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\Livewire\StandaloneCounter;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Alert;
use LaBoiteACode\DependencyGraph\Tests\Fixtures\View\Components\Shop\Price;

/**
 * @param  list<string>  $livewireIds
 */
function preparedResolver(array $livewireIds = [], mixed ...$overrides): ViewReferenceResolver
{
    $context = test()->fixtureContext(...$overrides);
    $resolver = app(ViewReferenceResolver::class);
    $resolver->prepare(app(BladeTemplateScanner::class)->templates($context), $context, $livewireIds);

    return $resolver;
}

it('resolves application, package and missing views', function (): void {
    $resolver = preparedResolver();

    expect($resolver->view('orders.partials.row'))
        ->kind->toBe(ViewResolution::APPLICATION_VIEW)
        ->id->toBe('view:orders.partials.row')
        ->and($resolver->view('filament-dependency-graph::page'))
        ->kind->toBe(ViewResolution::EXTERNAL)
        ->package->toBe('filament-dependency-graph')
        ->missing->toBeFalse()
        ->and($resolver->view('orders.partials.missing'))
        ->kind->toBe(ViewResolution::EXTERNAL)
        ->missing->toBeTrue()
        ->and($resolver->view('mail::message'))
        ->package->toBe('mail')
        ->missing->toBeFalse();
});

it('explores package views on request', function (): void {
    expect(preparedResolver(explorePackageViews: true)->view('filament-dependency-graph::page'))
        ->kind->toBe(ViewResolution::PACKAGE_VIEW)
        ->id->toBe('view:filament-dependency-graph::page')
        ->path->not->toBeNull();
});

it('resolves Blade components like the Blade compiler', function (): void {
    $resolver = preparedResolver();

    expect($resolver->component('alert'))
        ->kind->toBe(ViewResolution::BLADE_COMPONENT)
        ->value->toBe(Alert::class)
        ->and($resolver->component('shop::price'))
        ->kind->toBe(ViewResolution::BLADE_COMPONENT)
        ->value->toBe(Price::class)
        ->and($resolver->component('badge'))
        ->kind->toBe(ViewResolution::APPLICATION_VIEW)
        ->id->toBe('view:components.badge')
        ->and($resolver->component('filament::badge'))
        ->kind->toBe(ViewResolution::EXTERNAL)
        ->package->toBe('filament')
        ->and($resolver->component('does-not-exist'))
        ->missing->toBeTrue();
});

it('resolves Livewire components without instantiating them', function (): void {
    $resolver = preparedResolver([StableIdentifier::livewireComponent(StandaloneCounter::class)]);

    expect($resolver->livewire('standalone-counter'))
        ->kind->toBe(ViewResolution::LIVEWIRE)
        ->value->toBe(StandaloneCounter::class)
        ->and($resolver->livewire(StandaloneCounter::class))
        ->kind->toBe(ViewResolution::LIVEWIRE)
        ->and($resolver->livewire('unknown-component'))
        ->missing->toBeTrue();
});

it('keeps Livewire classes without a node as external leaves', function (): void {
    expect(preparedResolver()->livewire('standalone-counter'))
        ->kind->toBe(ViewResolution::EXTERNAL)
        ->value->toBe(StandaloneCounter::class)
        ->missing->toBeFalse();
});

it('records the externals and package views it hands out', function (): void {
    $resolver = preparedResolver(explorePackageViews: true);
    $resolver->view('orders.partials.missing');
    $resolver->view('filament-dependency-graph::page');

    expect(array_keys($resolver->externals()))->toBe(['external-view:orders.partials.missing'])
        ->and(array_keys($resolver->packageViews()))->toBe(['filament-dependency-graph::page']);
});
