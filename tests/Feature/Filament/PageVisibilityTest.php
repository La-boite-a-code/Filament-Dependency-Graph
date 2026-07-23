<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;
use LaBoiteACode\DependencyGraph\Filament\Pages\DependencyGraphPage;

function actingOnAdminPanel(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
}

it('registers the page on the admin panel through the plugin', function (): void {
    actingOnAdminPanel();

    expect(Filament::getPanel('admin')->getPages())->toContain(DependencyGraphPage::class);
});

it('hides the page outside the local environment by default', function (): void {
    actingOnAdminPanel();

    expect(app()->environment())->toBe('testing')
        ->and(DependencyGraphPage::canAccess())->toBeFalse();
});

it('shows the page in the local environment by default', function (): void {
    actingOnAdminPanel();

    app()->detectEnvironment(static fn (): string => 'local');

    expect(DependencyGraphPage::canAccess())->toBeTrue();
});

it('honors a custom visibility callback granting access', function (): void {
    actingOnAdminPanel();

    DependencyGraphPlugin::current()?->visible(static fn (): bool => true);

    expect(DependencyGraphPage::canAccess())->toBeTrue();
});

it('honors a custom visibility callback denying access', function (): void {
    actingOnAdminPanel();

    app()->detectEnvironment(static fn (): string => 'local');
    DependencyGraphPlugin::current()?->visible(static fn (): bool => false);

    expect(DependencyGraphPage::canAccess())->toBeFalse();
});

it('stays hidden when the package is disabled', function (): void {
    actingOnAdminPanel();

    app()->detectEnvironment(static fn (): string => 'local');
    config()->set('filament-dependency-graph.enabled', false);

    expect(DependencyGraphPage::canAccess())->toBeFalse();
});

it('is not registered outside a panel context', function (): void {
    expect(DependencyGraphPlugin::current())->not->toBeNull();
});
