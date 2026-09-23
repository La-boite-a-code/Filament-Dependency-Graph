<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Tests\Fixtures\Broken;

/**
 * Cannot be loaded: its parent class does not exist.
 */
final class BrokenListener extends MissingListenerParent {}
