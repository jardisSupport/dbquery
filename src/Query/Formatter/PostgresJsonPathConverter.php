<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Formatter;

/**
 * Converts MySQL-style JSON path notation to PostgreSQL notation
 *
 * MySQL uses $.path.to.field, PostgreSQL uses path.to.field
 * This converter strips the leading $ or $. prefix.
 *
 * Can be reused across all PostgreSQL SQL generators without side effects.
 */
class PostgresJsonPathConverter
{
    /**
     * Convert a JSON path from MySQL notation to PostgreSQL notation
     *
     * @param string $jsonPath JSON path (e.g., '$.user.name', '$name', 'age')
     * @return string PostgreSQL-compatible path (e.g., 'user.name', 'name', 'age')
     */
    public function __invoke(string $jsonPath): string
    {
        if (strpos($jsonPath, '$.') === 0) {
            return substr($jsonPath, 2);
        }

        if (strpos($jsonPath, '$') === 0) {
            return substr($jsonPath, 1);
        }

        return $jsonPath;
    }
}
