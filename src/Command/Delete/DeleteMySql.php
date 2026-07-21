<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Delete;

use JardisSupport\DbQuery\Data\Dialect;

/**
 * MySQL/MariaDB DELETE SQL Generator
 *
 * Supports DELETE statements with JOINs, WHERE, ORDER BY, LIMIT.
 * JSON functions require MySQL 5.7+ or MariaDB 10.2+.
 */
class DeleteMySql extends DeleteSqlBuilder
{
    protected string $dialect = Dialect::MySQL->value;

    /**
     * Quote identifier with backticks for MySQL
     *
     * @param string $identifier
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

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
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path (e.g., '$.age')
     * @return string The MySQL JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        return "JSON_EXTRACT(" . $this->quoteIdentifier($column) . ", '" . $this->escapeString($path) . "')";
    }

    /**
     * Builds JSON_CONTAINS expression for MySQL
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with ? placeholder)
     * @param string|null $path Optional JSON path
     * @return string The MySQL JSON contains an expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
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

    /**
     * Build DELETE clause for MySQL with JOIN support
     * MySQL syntax with JOINs: DELETE alias FROM table alias JOIN...
     * Without JOINs: DELETE FROM table
     *
     * @return string
     */
    protected function buildDelete(): string
    {
        $hasJoins = !empty($this->state->getJoins());
        $alias = $this->state->getAlias();

        if ($hasJoins && $alias !== null) {
            // MySQL DELETE with JOIN: DELETE alias FROM table alias
            return 'DELETE ' . $this->quoteIdentifier($alias)
                . ' FROM ' . $this->quoteIdentifier($this->state->getTable())
                . ' ' . $this->quoteIdentifier($alias) . ' ';
        }

        // Standard DELETE: DELETE FROM table [alias]
        $result = 'DELETE FROM ' . $this->quoteIdentifier($this->state->getTable());

        if ($alias !== null) {
            $result .= ' ' . $this->quoteIdentifier($alias);
        }

        return $result . ' ';
    }
}
