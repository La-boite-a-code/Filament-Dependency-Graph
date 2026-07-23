<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('caches the snapshot and reports counts', function (): void {
    $this->artisan('filament-dependency-graph:cache')
        ->expectsOutputToContain('Dependency graph snapshot cached.')
        ->expectsOutputToContain('Models')
        ->expectsOutputToContain('Warnings')
        ->assertSuccessful();
});

it('clears the cache', function (): void {
    $this->artisan('filament-dependency-graph:clear')
        ->expectsOutputToContain('Dependency graph cache cleared.')
        ->assertSuccessful();
});

it('rejects an invalid scope option', function (): void {
    $this->artisan('filament-dependency-graph:cache', ['--scope' => 'nope'])
        ->assertFailed();
});

it('exports json to standard output', function (): void {
    $this->artisan('filament-dependency-graph:export', ['--format' => 'json'])
        ->expectsOutputToContain('"schemaVersion": "1.0"')
        ->assertSuccessful();
});

it('exports mermaid to standard output', function (): void {
    $this->artisan('filament-dependency-graph:export', ['--format' => 'mermaid'])
        ->expectsOutputToContain('flowchart LR')
        ->assertSuccessful();
});

it('fails clearly for unknown formats', function (): void {
    $this->artisan('filament-dependency-graph:export', ['--format' => 'xml'])
        ->expectsOutputToContain('Unknown export format [xml]')
        ->assertFailed();
});

it('writes the export to a file', function (): void {
    $path = sys_get_temp_dir() . '/fdg-export-test/dependency-graph.json';
    File::deleteDirectory(dirname($path));

    $this->artisan('filament-dependency-graph:export', [
        '--format' => 'json',
        '--output' => $path,
    ])->assertSuccessful();

    expect(file_exists($path))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($path), true);

    expect($decoded['schemaVersion'])->toBe('1.0');

    File::deleteDirectory(dirname($path));
});

it('refuses to overwrite an existing file without force', function (): void {
    $path = sys_get_temp_dir() . '/fdg-export-test/dependency-graph.json';
    File::ensureDirectoryExists(dirname($path));
    file_put_contents($path, 'existing');

    $this->artisan('filament-dependency-graph:export', [
        '--format' => 'json',
        '--output' => $path,
    ])->assertFailed();

    expect(file_get_contents($path))->toBe('existing');

    $this->artisan('filament-dependency-graph:export', [
        '--format' => 'json',
        '--output' => $path,
        '--force' => true,
    ])->assertSuccessful();

    expect(file_get_contents($path))->not->toBe('existing');

    File::deleteDirectory(dirname($path));
});

it('supports focus options on export', function (): void {
    $this->artisan('filament-dependency-graph:export', [
        '--format' => 'json',
        '--scope' => 'laravel',
        '--focus' => 'model:la-boite-a-code.dependency-graph.tests.fixtures.models.order',
        '--depth' => '1',
        '--direction' => 'outgoing',
    ])->assertSuccessful();
});
