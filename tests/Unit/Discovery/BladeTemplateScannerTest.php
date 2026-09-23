<?php

declare(strict_types=1);

use LaBoiteACode\DependencyGraph\Discovery\Views\BladeTemplateScanner;

function scannedTemplate(string $contents): array
{
    return array_map(
        static fn (array $reference): string => sprintf(
            '%d %s %s %s',
            $reference['line'],
            $reference['type'],
            $reference['directive'],
            $reference['written'] ?? ('{' . $reference['expression'] . '}'),
        ),
        app(BladeTemplateScanner::class)->references($contents),
    );
}

it('extracts directives and component tags with their line', function (): void {
    $contents = (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/views/orders/index.blade.php');

    expect(scannedTemplate($contents))->toBe([
        '1 extends @extends layouts.app',
        '5 component <x-alert> alert',
        '6 component <x-filament::badge> filament::badge',
        '9 include @include orders.partials.row',
        '12 include @each orders.partials.row',
        '12 include @each orders.partials.empty',
        '13 dynamic @include {$customPartial}',
        '14 include @includeIf orders.partials.missing',
        '15 dynamic <x-dynamic-component> {$widget}',
    ]);
});

it('reads every candidate of includeFirst and the view of includeWhen', function (): void {
    expect(scannedTemplate(<<<'BLADE'
        @includeFirst(['partials.custom', "partials.default"], ['a' => 1])
        @includeWhen($user->isAdmin(), 'partials.admin', ['user' => $user])
        @includeUnless($hidden, 'partials.banner')
        @component('components.card', ['title' => 'x'])
        @endcomponent
        BLADE))->toBe([
        '1 include @includeFirst partials.custom',
        '1 include @includeFirst partials.default',
        '2 include @includeWhen partials.admin',
        '3 include @includeUnless partials.banner',
        '4 include @component components.card',
    ]);
});

it('reads Livewire tags and directives, and skips non-component tags', function (): void {
    expect(scannedTemplate(<<<'BLADE'
        <livewire:cart :items="$items" />
        @livewire('shop.checkout', ['order' => $order])
        @livewire(\App\Livewire\Profile::class)
        <livewire:styles />
        <livewire:is :component="$current" />
        <x-slot:footer>Footer</x-slot:footer>
        <x-slot name="header">Header</x-slot>
        BLADE))->toBe([
        '1 livewire <livewire:cart> cart',
        '2 livewire @livewire shop.checkout',
        '3 livewire @livewire App\Livewire\Profile',
        '5 dynamic <livewire:is> {$current}',
    ]);
});

it('ignores comments, verbatim blocks, PHP blocks, escaped directives and raw each values', function (): void {
    expect(scannedTemplate(<<<'BLADE'
        {{-- @include('comment') <x-comment /> --}}
        @verbatim @include('verbatim') @endverbatim
        @php $html = '<x-in-php />'; @endphp
        <?php $view = view('in-php'); ?>
        @@include('escaped')
        @each('rows.row', $rows, 'row', 'raw|<p>None</p>')
        BLADE))->toBe([
        '6 include @each rows.row',
    ]);
});

it('derives view names and recognises Livewire single-file components', function (): void {
    $scanner = app(BladeTemplateScanner::class);
    $single = (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/views-livewire4/livewire/⚡counter.blade.php');

    expect($scanner->nameFromPath('orders/partials/row.blade.php'))->toBe('orders.partials.row')
        ->and($scanner->nameFromPath('livewire/⚡counter.blade.php'))->toBe('livewire.counter')
        ->and($scanner->isLivewireSingleFile($single))->toBeTrue()
        ->and($scanner->isLivewireSingleFile('<div>@include("x")</div>'))->toBeFalse();
});

it('lists the application templates of the view paths', function (): void {
    $templates = app(BladeTemplateScanner::class)->templates($this->fixtureContext(excludedViews: ['unused']));

    expect(array_keys($templates))->toContain('layouts.app', 'orders.index', 'components.shop.price', 'mail.order-shipped')
        ->not->toContain('unused');
});
