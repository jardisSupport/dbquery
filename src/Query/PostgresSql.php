<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Query\Formatter\PostgresJsonPathConverter;

/**
 * PostgresSQL SQL Generator
 *
 * Requires PostgresSQL 8.4+ for CTE support
 * JSON functions require PostgresSQL 9.2+, JSONB functions require 9.4+
 */
class PostgresSql extends SqlBuilder
{
    protected string $dialect = Dialect::PostgreSQL->value;

    /**
     * PostgresSQL uses TRUE/FALSE for booleans
     *
     * @param bool $value
     * @return string
     */
    protected function formatBoolean(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }

    /**
     * Quote identifier with double quotes (PostgresSQL standard)
     *
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Builds JSON path extraction for PostgresSQL
     * Uses ->> operator for text extraction or -> for JSON extraction
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age' or 'age')
     * @return string The PostgresSQL JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        // PostgreSQL uses different operators than JSON path
        // Convert $.path notation to PostgreSQL notation
        $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);

        // Use ->> operator to extract as text (for comparisons)
        // If multiple levels, we need to chain operators
        if (strpos($pgPath, '.') !== false) {
            $parts = explode('.', $pgPath);
            $result = $this->quoteIdentifier($column);

            foreach ($parts as $index => $part) {
                $isLast = ($index === count($parts) - 1);
                $operator = $isLast ? '->>' : '->';
                $result .= $operator . "'" . $this->escapeString($part) . "'";
            }

            return $result;
        }

        return $this->quoteIdentifier($column) . "->>'" . $this->escapeString($pgPath) . "'";
    }

    /**
     * Builds JSON contains a check for PostgresSQL
     * Uses @> operator (containment operator for JSONB)
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL JSON contains an expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        if ($path !== null) {
            // For path-specific contents, extract the path first then check
            $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);
            return $this->quoteIdentifier($column)
                . "->'" . $this->escapeString($pgPath) . "' @> "
                . "to_jsonb(" . $value . "::text)";
        }

        // Direct containment check using @> operator
        // Cast to text first so PostgreSQL can determine the type for prepared statement bindings
        return $this->quoteIdentifier($column) . " @> to_jsonb(" . $value . "::text)";
    }

    /**
     * Builds negated JSON contains for PostgresSQL
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL negated contains expression
     */
    protected function buildJsonNotContains(string $column, string $value, ?string $path): string
    {
        if ($path !== null) {
            $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);
            return "NOT (" . $this->quoteIdentifier($column)
                . "->'" . $this->escapeString($pgPath) . "' @> "
                . "to_jsonb(" . $value . "::text))";
        }

        return "NOT (" . $this->quoteIdentifier($column) . " @> to_jsonb(" . $value . "::text))";
    }

    /**
     * PostgreSQL with standard_conforming_strings=on (default since 9.1)
     * treats backslash as literal, so no backslash escaping needed
     *
     * @param string $value
     * @return string
     */
    protected function escapeString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * Builds JSON length expression for PostgresSQL
     * Uses jsonb_array_length() or jsonb_object_keys()
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        if ($path !== null) {
            $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);
            // Extract path and get array length
            return "jsonb_array_length("
                . $this->quoteIdentifier($column)
                . "->'" . $this->escapeString($pgPath) . "')";
        }

        // For root-level length
        return "jsonb_array_length(" . $this->quoteIdentifier($column) . ")";
    }
}
