<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;

/**
 * Represents a prepared SQL query with placeholders and bindings
 *
 * This class implements the DbPreparedQueryInterface and provides
 * the SQL statement with placeholders (?) and the corresponding
 * parameter bindings for safe execution via PDO or similar.
 *
 * All properties are readonly to ensure immutability of prepared queries.
 */
class DbPreparedQuery implements DbPreparedQueryInterface
{
    /**
     * @param string $sql The SQL query string with placeholders
     * @param array<int|string, mixed> $bindings The array of bindings for the query
     * @param string $type The database dialect (mysql, postgres, sqlite, mariadb)
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $bindings,
        private readonly string $type
    ) {
    }

    /**
     * Returns the SQL query string with placeholders
     *
     * @return string SQL statement with placeholders
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * Returns the parameter bindings for the prepared statement
     *
     * @return array<int|string, mixed> Array of values to bind to placeholders
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * Retrieves the type as a string.
     *
     * @return string The type of the object.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Returns a string representation of the prepared query
     * Useful for debugging purposes
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->sql;
    }
}
