<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\Contract\FromStateInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless builder for FROM clause
 *
 * Builds the FROM portion of a SQL query including subqueries and aliases.
 * Can be reused across multiple queries without side effects.
 */
class FromBuilder
{
    /**
     * Builds FROM clause with table name or subquery
     *
     * @param FromStateInterface $state The query state containing container and alias
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to use prepared statement mode
     * @param callable $quoteIdentifier Callback to quote identifiers: fn(string): string
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @return string The FROM clause
     */
    public function __invoke(
        FromStateInterface $state,
        string $dialect,
        bool $prepared,
        callable $quoteIdentifier,
        array &$bindings
    ): string {
        if (empty($state->getContainer())) {
            return '';
        }

        if ($state->getContainer() instanceof DbQueryBuilderInterface) {
            $subResult = $state->getContainer()->sql($dialect, $prepared);

            if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                $containerStr = '(' . $subResult->sql() . ')';
                $bindings = array_merge($bindings, $subResult->bindings());
            } else {
                /** @phpstan-ignore binaryOp.invalid */
                $containerStr = '(' . $subResult . ')';
            }
        } else {
            $containerStr = $quoteIdentifier($state->getContainer());
        }

        $aliasStr = $state->getAlias() ? ' ' . $quoteIdentifier($state->getAlias()) : '';

        return ' FROM ' . trim($containerStr . $aliasStr);
    }
}
