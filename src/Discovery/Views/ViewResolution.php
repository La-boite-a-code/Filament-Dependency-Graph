<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Views;

/**
 * What a name written in a template, or rendered by a class, points to.
 */
final readonly class ViewResolution
{
    public const APPLICATION_VIEW = 'view';

    public const PACKAGE_VIEW = 'package_view';

    public const BLADE_COMPONENT = 'blade_component';

    public const LIVEWIRE = 'livewire';

    public const EXTERNAL = 'external';

    public function __construct(
        public string $kind,
        public string $id,
        public string $value,
        public ?string $path = null,
        public ?string $package = null,
        public bool $missing = false,
        public bool $livewireView = false,
    ) {}
}
