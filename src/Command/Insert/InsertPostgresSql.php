<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * PostgresSQL INSERT SQL Generator
 *
 * Uses double-quote quoting for identifiers.
 * Supports PostgresSQL-specific features like RETURNING clause (future).
 */
class InsertPostgresSql extends InsertSqlBuilder
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
     * Build ON CONFLICT DO NOTHING for PostgreSQL (instead of ON DUPLICATE KEY UPDATE)
     *
     * Override base method to handle Postgres-specific conflict resolution
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildOnDuplicateKeyUpdate(bool $prepared): string
    {
        // Postgres uses ON CONFLICT DO NOTHING for orIgnore
        if ($this->state->isOrIgnore()) {
            return ' ON CONFLICT DO NOTHING';
        }

        // ON DUPLICATE KEY UPDATE not supported in Postgres
        // Would need ON CONFLICT (column) DO UPDATE which requires knowing the constraint
        return '';
    }

    /**
     * Build ON CONFLICT clause for PostgreSQL
     *
     * Supports:
     * - ON CONFLICT (columns) DO UPDATE SET ...
     * - ON CONFLICT (columns) DO NOTHING
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildOnConflict(bool $prepared): string
    {
        $conflictColumns = $this->state->getOnConflictColumns();

        if (empty($conflictColumns)) {
            return '';
        }

        // Quote conflict columns
        $quotedColumns = array_map(
            fn($col) => $this->quoteIdentifier($col),
            $conflictColumns
        );

        $conflictClause = ' ON CONFLICT (' . implode(', ', $quotedColumns) . ')';

        // Handle DO NOTHING
        if ($this->state->isDoNothing()) {
            return $conflictClause . ' DO NOTHING';
        }

        // Handle DO UPDATE
        $updateFields = $this->state->getDoUpdateFields();

        if (empty($updateFields)) {
            return '';
        }

        $setParts = [];
        foreach ($updateFields as $field => $value) {
            $quotedField = $this->quoteIdentifier($field);

            if ($value instanceof ExpressionInterface) {
                $setParts[] = $quotedField . ' = ' . $value->toSql();
            } else {
                if ($prepared) {
                    $this->bindings[] = $value;
                    $setParts[] = $quotedField . ' = ?';
                } else {
                    $formattedValue = $this->formatValue($value);
                    $setParts[] = $quotedField . ' = ' . $formattedValue;
                }
            }
        }

        return $conflictClause . ' DO UPDATE SET ' . implode(', ', $setParts);
    }
}
