<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Discovery\Support;

use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Reads column nullability from database schema metadata.
 *
 * Inspection failures never break discovery: a table whose schema cannot be
 * read simply reports unknown nullability.
 */
final class SchemaInspector
{
    /** @var array<string, array<string, bool>|null> */
    private array $tables = [];

    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Returns null when nullability cannot be verified.
     */
    public function isColumnNullable(string $connection, string $table, string $column): ?bool
    {
        $columns = $this->columns($connection, $table);

        if ($columns === null) {
            return null;
        }

        return $columns[strtolower($column)] ?? null;
    }

    /**
     * Whether schema metadata could be read for the given table.
     */
    public function isAvailable(string $connection, string $table): bool
    {
        return $this->columns($connection, $table) !== null;
    }

    public function flush(): void
    {
        $this->tables = [];
    }

    /**
     * @return array<string, bool>|null
     */
    private function columns(string $connection, string $table): ?array
    {
        $key = $connection . '.' . $table;

        if (array_key_exists($key, $this->tables)) {
            return $this->tables[$key];
        }

        try {
            $builder = $this->database
                ->connection($connection === 'default' ? null : $connection)
                ->getSchemaBuilder();

            $map = [];

            foreach ($builder->getColumns($table) as $column) {
                $map[strtolower((string) $column['name'])] = (bool) $column['nullable'];
            }

            return $this->tables[$key] = $map;
        } catch (Throwable) {
            return $this->tables[$key] = null;
        }
    }
}
