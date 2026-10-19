<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\Dialect;

/**
 * MySQL/MariaDB INSERT SQL Generator
 *
 * Uses backtick quoting for identifiers.
 * Supports all MySQL INSERT features.
 */
class InsertMySql extends InsertSqlBuilder
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
     * Build OR IGNORE for MySQL (INSERT IGNORE)
     *
     * @return string
     */
    protected function buildOrIgnoreInsert(): string
    {
        return 'INSERT IGNORE';
    }

    /**
     * Build REPLACE for MySQL
     *
     * @return string
     */
    protected function buildReplaceInsert(): string
    {
        return 'REPLACE';
    }

    /**
     * ON DUPLICATE KEY UPDATE - MySQL/MariaDB pick the conflicting unique key themselves.
     *
     * All upsert entry points render here, so no capability is silently dropped:
     * - onDuplicateKeyUpdate() and onConflict()->doUpdate() -> ON DUPLICATE KEY UPDATE col = ?
     *   (both merge, onDuplicateKeyUpdate() wins per column)
     * - onConflict(col, ...)->doNothing() -> ON DUPLICATE KEY UPDATE col = col, a no-op that
     *   skips only the duplicate-key case. INSERT IGNORE is deliberately not used: it also
     *   downgrades NOT NULL, FK and truncation errors to warnings.
     *
     * @param bool $prepared
     * @return string
     * @throws InvalidArgumentException if doNothing() is used without a conflict target
     */
    protected function buildOnDuplicateKeyUpdate(bool $prepared): string
    {
        if ($this->state->isDoNothing()) {
            $columns = $this->state->getOnConflictColumns();
            if (empty($columns)) {
                throw new InvalidArgumentException(
                    'doNothing() on ' . $this->dialect . ' needs a conflict target: call onConflict(...columns) first'
                );
            }
            $column = $this->quoteIdentifier($columns[0]);

            return ' ON DUPLICATE KEY UPDATE ' . $column . ' = ' . $column;
        }

        $fields = $this->upsertFields();
        if (empty($fields)) {
            return '';
        }

        return ' ON DUPLICATE KEY UPDATE ' . $this->buildUpdateAssignments($fields, $prepared);
    }
}
