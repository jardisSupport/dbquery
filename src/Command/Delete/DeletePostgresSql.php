<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Delete;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Query\Formatter\PostgresJsonPathConverter;

/**
 * PostgresSQL DELETE SQL Generator
 *
 * PostgresSQL DELETE supports WHERE conditions but not JOINs, ORDER BY, or LIMIT.
 * For complex multi-table deletes, use DELETE with WHERE EXISTS subquery.
 * JSON operations use native PostgresSQL JSON operators (->>, ->, etc.).
 */
class DeletePostgresSql extends DeleteSqlBuilder
{
    protected string $dialect = Dialect::PostgreSQL->value;

    /**
     * Quote identifier with double quotes for PostgresSQL
     *
     * @param string $identifier
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Builds JSON extract expression for PostgresSQL using ->> operator
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age' or 'age')
     * @return string The PostgresSQL JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        /** @var PostgresJsonPathConverter $converter */
        $converter = $this->registry->get(PostgresJsonPathConverter::class);
        $pgPath = $converter($path);

        $parts = explode('.', $pgPath);

        if (count($parts) === 1) {
            return $this->quoteIdentifier($column) . "->>'" . $this->escapeString($parts[0]) . "'";
        }

        $result = $this->quoteIdentifier($column);
        $lastIndex = count($parts) - 1;

        foreach ($parts as $index => $part) {
            if ($index === $lastIndex) {
                $result .= "->>'" . $this->escapeString($part) . "'";
            } else {
                $result .= "->'" . $this->escapeString($part) . "'";
            }
        }

        return $result;
    }

    /**
     * Builds JSON contains an expression for PostgresSQL using @> operator
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with ? placeholder)
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL JSON contains an expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        if ($path !== null) {
            /** @var PostgresJsonPathConverter $converter */
            $converter = $this->registry->get(PostgresJsonPathConverter::class);
            $pgPath = $converter($path);
            return $this->quoteIdentifier($column) . "->'" . $this->escapeString($pgPath)
                . "' @> to_jsonb(" . $value . "::text)";
        }

        return $this->quoteIdentifier($column) . " @> to_jsonb(" . $value . "::text)";
    }

    /**
     * Builds JSON length expression for PostgresSQL using jsonb_array_length
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The PostgresSQL JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        if ($path !== null) {
            /** @var PostgresJsonPathConverter $converter */
            $converter = $this->registry->get(PostgresJsonPathConverter::class);
            $pgPath = $converter($path);
            return "jsonb_array_length(" . $this->quoteIdentifier($column) . "->'"
                . $this->escapeString($pgPath) . "')";
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
     * PostgreSQL does not support ORDER BY in DELETE statements
     *
     * @return string
     * @throws \InvalidArgumentException If ORDER BY was specified
     */
    protected function buildOrderBy(): string
    {
        if (!empty($this->state->getOrderBy())) {
            throw new \InvalidArgumentException('ORDER BY is not supported in PostgreSQL DELETE statements');
        }

        return '';
    }

    /**
     * PostgreSQL does not support LIMIT in DELETE statements
     *
     * @return string
     * @throws \InvalidArgumentException If LIMIT was specified
     */
    protected function buildLimit(): string
    {
        if ($this->state->getLimit() !== null) {
            throw new \InvalidArgumentException('LIMIT is not supported in PostgreSQL DELETE statements');
        }

        return '';
    }

    /**
     * PostgreSQL does not support JOIN in DELETE statements (with standard syntax)
     *
     * @param bool $prepared
     * @return string
     * @throws \InvalidArgumentException If JOIN was specified
     */
    protected function buildJoins(bool $prepared): string
    {
        if (!empty($this->state->getJoins())) {
            throw new \InvalidArgumentException('JOIN is not supported in PostgreSQL DELETE statements');
        }

        return '';
    }
}
