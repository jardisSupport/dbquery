<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;

/**
 * Stateless builder for SELECT clause
 *
 * Builds the SELECT portion of a SQL query including DISTINCT and subqueries.
 * Can be reused across multiple queries without side effects.
 */
class SelectBuilder
{
    /**
     * Builds SELECT clause with fields and optional subqueries
     *
     * @param QueryState $state The query state containing fields and subqueries
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to use prepared statement mode
     * @param callable $quoteIdentifier Callback to quote identifiers: fn(string): string
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @return string The SELECT clause
     */
    public function __invoke(
        QueryState $state,
        string $dialect,
        bool $prepared,
        callable $quoteIdentifier,
        array &$bindings
    ): string {
        $distinct = $state->isDistinct() ? 'DISTINCT ' : '';
        $selectFields = trim($state->getFields());

        $additionalFields = [];

        // Handle SELECT subqueries
        if (!empty($state->getSelectSubqueries())) {
            $selectSubqueryBindings = [];

            foreach ($state->getSelectSubqueries() as $alias => $query) {
                $subResult = $query->sql($dialect, $prepared);

                if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                    $additionalFields[] = '(' . $subResult->sql() . ') AS ' . $quoteIdentifier($alias);
                    $selectSubqueryBindings = array_merge($selectSubqueryBindings, $subResult->bindings());
                } else {
                    /** @phpstan-ignore binaryOp.invalid */
                    $additionalFields[] = '(' . $subResult . ') AS ' . $quoteIdentifier($alias);
                }
            }

            // Insert SELECT subquery bindings BEFORE main query bindings
            if (!empty($selectSubqueryBindings)) {
                $bindings = array_merge($selectSubqueryBindings, $bindings);
            }
        }

        // Handle window functions
        foreach ($state->getWindowFunctions() as $windowFunc) {
            $funcCall = $windowFunc->getFunction() . '(';
            if ($windowFunc->getArgs() !== null) {
                $funcCall .= $windowFunc->getArgs();
            }
            $funcCall .= ')';

            // Build OVER clause from WindowSpec
            $funcCall .= ' OVER (' . $windowFunc->getSpec()->toSql() . ')';

            $additionalFields[] = $funcCall . ' AS ' . $quoteIdentifier($windowFunc->getAlias());
        }

        // Handle window references
        foreach ($state->getWindowReferences() as $windowRef) {
            $funcCall = $windowRef->getFunction() . '(';
            if ($windowRef->getArgs() !== null) {
                $funcCall .= $windowRef->getArgs();
            }
            $funcCall .= ')';

            $funcCall .= ' OVER ' . $windowRef->getWindowName();

            $additionalFields[] = $funcCall . ' AS ' . $quoteIdentifier($windowRef->getAlias());
        }

        // Combine all fields
        if (!empty($additionalFields)) {
            if ($selectFields !== '' && $selectFields !== '*') {
                $selectFields .= ', ' . implode(', ', $additionalFields);
            } else {
                $selectFields = implode(', ', $additionalFields);
            }
        }

        return 'SELECT ' . $distinct . $selectFields;
    }
}
