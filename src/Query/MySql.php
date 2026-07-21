<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query;

use JardisSupport\DbQuery\Data\Dialect;

/**
 * MySQL/MariaDB SQL Generator
 *
 * Requires MySQL 8.0+ or MariaDB 10.2+ for CTE support.
 * JSON functions require MySQL 5.7+ or MariaDB 10.2+.
 *
 * Most SQL features work with MySQL 5.7+ (SELECT, JOIN, GROUP BY, HAVING,
 * ORDER BY, LIMIT, OFFSET, DISTINCT, UNION, subqueries, EXISTS clauses).
 * Only CTE (WITH clause) requires MySQL 8.0+.
 */
class MySql extends SqlBuilder
{
    protected string $dialect = Dialect::MySQL->value;

    /**
     * MySQL does not support FULL OUTER JOIN
     *
     * @param string $joinType
     * @return bool
     */
    protected function shouldSkipJoinType(string $joinType): bool
    {
        return $joinType === 'FULL JOIN' || $joinType === 'FULL OUTER JOIN';
    }

    /**
     * Builds JSON_EXTRACT expression for MySQL
     * Uses JSON_EXTRACT() function or -> operator (MySQL 5.7+)
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age')
     * @return string The MySQL JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        // MySQL supports both JSON_EXTRACT() and -> operator
        // We use JSON_EXTRACT for consistency and to handle nested paths
        return "JSON_EXTRACT(" . $this->quoteIdentifier($column) . ", '" . $this->escapeString($path) . "')";
    }

    /**
     * Builds JSON_CONTAINS expression for MySQL
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The MySQL JSON contains an expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        // MySQL JSON_CONTAINS expects JSON as the second parameter
        // JSON_QUOTE wraps the value as a proper JSON string value
        if ($path !== null) {
            return "JSON_CONTAINS("
                . $this->quoteIdentifier($column)
                . ", JSON_QUOTE(" . $value . ")"
                . ", '" . $this->escapeString($path) . "')";
        }

        return "JSON_CONTAINS("
            . $this->quoteIdentifier($column)
            . ", JSON_QUOTE(" . $value . "))";
    }

    /**
     * Builds JSON_LENGTH expression for MySQL
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The MySQL JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        if ($path !== null) {
            return "JSON_LENGTH("
                . $this->quoteIdentifier($column)
                . ", '" . $this->escapeString($path) . "')";
        }

        return "JSON_LENGTH(" . $this->quoteIdentifier($column) . ")";
    }
}
