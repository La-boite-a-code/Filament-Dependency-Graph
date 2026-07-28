<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Domain;

/**
 * Version of the serialized graph schema, shared by exports and cache keys.
 * Bump it whenever the serialized shape of snapshots or graphs changes.
 */
final class SchemaVersion
{
    public const CURRENT = '1.1';
}
