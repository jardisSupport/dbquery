<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query;

use JardisSupport\DbQuery\Data\Dialect;

/**
 * SQLite SQL Generator
 *
 * Requires SQLite 3.8.3+ for CTE support
 * JSON functions require SQLite 3.38.0+ (2022-02-22)
 */
class SqliteSql extends SqlBuilder
{
    protected string $dialect = Dialect::SQLite->value;

    /**
     * SQLite does not support FULL OUTER JOIN
     *
     * @param string $joinType
     * @return bool
     */
    protected function shouldSkipJoinType(string $joinType): bool
    {
        return $joinType === 'FULL JOIN' || $joinType === 'FULL OUTER JOIN';
    }

    /**
     * Builds JSON_EXTRACT expression for SQLite
     * Uses json_extract() function (SQLite 3.38+)
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age')
     * @return string The SQLite JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        // SQLite uses json_extract() with $ path notation
        return "json_extract("
            . $this->quoteIdentifier($column)
            . ", '" . $this->escapeString($path) . "')";
    }

    /**
     * Builds JSON contains check for SQLite
     * SQLite doesn't have JSON_CONTAINS, so we simulate it
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The SQLite JSON contains expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        // SQLite doesn't have JSON_CONTAINS, we need to simulate it
        // We check if the JSON type is 'array' and use json_each to search
        if ($path !== null) {
            // For path-specific search, extract the path first
            return "EXISTS (SELECT 1 FROM json_each("
                . "json_extract(" . $this->quoteIdentifier($column)
                . ", '" . $this->escapeString($path) . "')"
                . ") WHERE value = " . $value . ")";
        }

        // For root-level search
        return "EXISTS (SELECT 1 FROM json_each("
            . $this->quoteIdentifier($column)
            . ") WHERE value = " . $value . ")";
    }

    /**
     * Builds negated JSON contains for SQLite
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The SQLite negated contains expression
     */
    protected function buildJsonNotContains(string $column, string $value, ?string $path): string
    {
        // Simply negate the EXISTS clause
        if ($path !== null) {
            return "NOT EXISTS (SELECT 1 FROM json_each("
                . "json_extract(" . $this->quoteIdentifier($column)
                . ", '" . $this->escapeString($path) . "')"
                . ") WHERE value = " . $value . ")";
        }

        return "NOT EXISTS (SELECT 1 FROM json_each("
            . $this->quoteIdentifier($column)
            . ") WHERE value = " . $value . ")";
    }

    /**
     * Builds JSON length expression for SQLite
     * Uses json_array_length() function
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The SQLite JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        if ($path !== null) {
            // For path-specific length, extract a path first
            return "json_array_length("
                . "json_extract(" . $this->quoteIdentifier($column)
                . ", '" . $this->escapeString($path) . "')"
                . ")";
        }

        return "json_array_length(" . $this->quoteIdentifier($column) . ")";
    }
}
