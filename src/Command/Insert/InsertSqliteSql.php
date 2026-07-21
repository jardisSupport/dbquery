<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * SQLite INSERT SQL Generator
 *
 * Uses backtick quoting for identifiers (though SQLite also accepts double quotes).
 * Supports SQLite-specific features.
 */
class InsertSqliteSql extends InsertSqlBuilder
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
     * Build OR IGNORE for SQLite
     *
     * @return string
     */
    protected function buildOrIgnoreInsert(): string
    {
        return 'INSERT OR IGNORE';
    }

    /**
     * Build REPLACE for SQLite
     *
     * @return string
     */
    protected function buildReplaceInsert(): string
    {
        return 'INSERT OR REPLACE';
    }

    /**
     * Build ON CONFLICT clause for SQLite
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
