<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert;

use JardisSupport\DbQuery\Data\Dialect;

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
     * ON CONFLICT (columns) DO UPDATE SET ... | DO NOTHING
     *
     * Reached through onConflict()->doUpdate() and onDuplicateKeyUpdate() alike.
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildOnConflict(bool $prepared): string
    {
        return $this->buildOnConflictClause($prepared);
    }
}
