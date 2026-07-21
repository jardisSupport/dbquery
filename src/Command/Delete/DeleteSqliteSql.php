<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Delete;

use JardisSupport\DbQuery\Data\Dialect;

/**
 * SQLite DELETE it SQL Generator
 *
 * SQLite DELETE supports WHERE conditions and limited JOIN support.
 * ORDER BY and LIMIT are supported in SQLite 3.24.0+.
 * JSON functions require SQLite 3.38.0+ with JSON1 extension.
 */
class DeleteSqliteSql extends DeleteSqlBuilder
{
    protected string $dialect = Dialect::SQLite->value;

    /**
     * Quote identifier with backticks for SQLite
     *
     * @param string $identifier
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Builds JSON extract expression for SQLite using json_extract
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age')
     * @return string The SQLite JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        return "json_extract(" . $this->quoteIdentifier($column) . ", '" . $this->escapeString($path) . "')";
    }

    /**
     * Builds JSON contains check for SQLite using json_each for exact value matching
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with ? placeholder)
     * @param string|null $path Optional JSON path
     * @return string The SQLite JSON contains expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        if ($path !== null) {
            return "EXISTS (SELECT 1 FROM json_each("
                . "json_extract(" . $this->quoteIdentifier($column)
                . ", '" . $this->escapeString($path) . "')"
                . ") WHERE value = " . $value . ")";
        }

        return "EXISTS (SELECT 1 FROM json_each("
            . $this->quoteIdentifier($column)
            . ") WHERE value = " . $value . ")";
    }

    /**
     * Builds negated JSON contains for SQLite using NOT EXISTS with json_each
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with ? placeholder)
     * @param string|null $path Optional JSON path
     * @return string The SQLite negated contains expression
     */
    protected function buildJsonNotContains(string $column, string $value, ?string $path): string
    {
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
     * Builds JSON length expression for SQLite using json_array_length
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The SQLite JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        if ($path !== null) {
            return "json_array_length(" . $this->quoteIdentifier($column) . ", '" . $this->escapeString($path) . "')";
        }

        return "json_array_length(" . $this->quoteIdentifier($column) . ")";
    }

    /**
     * SQLite does not support ORDER BY in DELETE statements
     *
     * @return string
     * @throws \InvalidArgumentException If ORDER BY was specified
     */
    protected function buildOrderBy(): string
    {
        if (!empty($this->state->getOrderBy())) {
            throw new \InvalidArgumentException('ORDER BY is not supported in SQLite DELETE statements');
        }

        return '';
    }

    /**
     * SQLite does not support LIMIT in DELETE statements
     *
     * @return string
     * @throws \InvalidArgumentException If LIMIT was specified
     */
    protected function buildLimit(): string
    {
        if ($this->state->getLimit() !== null) {
            throw new \InvalidArgumentException('LIMIT is not supported in SQLite DELETE statements');
        }

        return '';
    }

    /**
     * SQLite does not support JOIN in DELETE statements (with standard syntax)
     *
     * @param bool $prepared
     * @return string
     * @throws \InvalidArgumentException If JOIN was specified
     */
    protected function buildJoins(bool $prepared): string
    {
        if (!empty($this->state->getJoins())) {
            throw new \InvalidArgumentException('JOIN is not supported in SQLite DELETE statements');
        }

        return '';
    }
}
