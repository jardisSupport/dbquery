<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert;

use JardisSupport\DbQuery\Data\Dialect;

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
     * PostgreSQL has no ON DUPLICATE KEY UPDATE; orIgnore() maps to ON CONFLICT DO NOTHING.
     * The upsert form is rendered by buildOnConflict().
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildOnDuplicateKeyUpdate(bool $prepared): string
    {
        if ($this->state->isOrIgnore()) {
            return ' ON CONFLICT DO NOTHING';
        }

        return '';
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
