<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Update;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Query\Formatter\PostgresJsonPathConverter;

/**
 * PostgresSQL UPDATE SQL Generator
 *
 * Requires PostgresSQL 8.4+
 * JSON functions require PostgresSQL 9.2+, JSONB functions require 9.4+
 *
 * Note: PostgresSQL supports FROM clause in UPDATE for joins,
 * but this implementation uses standard UPDATE syntax compatible
 * with the JoinBuilder pattern (UPDATE...JOIN is MySQL-specific).
 */
class UpdatePostgresSql extends UpdateSqlBuilder
{
    protected string $dialect = Dialect::PostgreSQL->value;

    /**
     * Quote identifier with double quotes (PostgresSQL standard)
     *
     * @param string $identifier
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

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
     * Builds JSON path extraction for PostgresSQL
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age' or 'age')
     * @return string The PostgresSQL JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);

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
     * Builds JSON contains check for PostgresSQL
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with ? placeholder)
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL JSON contains an expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        if ($path !== null) {
            $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);
            return $this->quoteIdentifier($column)
                . "->'" . $this->escapeString($pgPath) . "' @> "
                . "to_jsonb(" . $value . "::text)";
        }

        return $this->quoteIdentifier($column) . " @> to_jsonb(" . $value . "::text)";
    }

    /**
     * Builds negated JSON contains for PostgresSQL
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with ? placeholder)
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
     * Builds JSON length expression for PostgresSQL
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        if ($path !== null) {
            $pgPath = $this->registry->get(PostgresJsonPathConverter::class)($path);
            return "jsonb_array_length("
                . $this->quoteIdentifier($column)
                . "->'" . $this->escapeString($pgPath) . "')";
        }

        return "jsonb_array_length(" . $this->quoteIdentifier($column) . ")";
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
     * PostgreSQL does not support ORDER BY in UPDATE statements
     *
     * @return string
     * @throws \InvalidArgumentException If ORDER BY was specified
     */
    protected function buildOrderBy(): string
    {
        if (!empty($this->state->getOrderBy())) {
            throw new \InvalidArgumentException('ORDER BY is not supported in PostgreSQL UPDATE statements');
        }

        return '';
    }

    /**
     * PostgreSQL does not support LIMIT in UPDATE statements
     *
     * @return string
     * @throws \InvalidArgumentException If LIMIT was specified
     */
    protected function buildLimit(): string
    {
        if ($this->state->getLimit() !== null) {
            throw new \InvalidArgumentException('LIMIT is not supported in PostgreSQL UPDATE statements');
        }

        return '';
    }

    /**
     * PostgreSQL does not support JOIN in UPDATE statements (with standard syntax)
     *
     * @param bool $prepared
     * @return string
     * @throws \InvalidArgumentException If JOIN was specified
     */
    protected function buildJoins(bool $prepared): string
    {
        if (!empty($this->state->getJoins())) {
            throw new \InvalidArgumentException('JOIN is not supported in PostgreSQL UPDATE statements');
        }

        return '';
    }
}
