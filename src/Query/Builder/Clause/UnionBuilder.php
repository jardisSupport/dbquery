<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;

/**
 * Stateless builder for UNION clauses
 *
 * Builds UNION and UNION ALL clauses for SQL queries.
 * Can be reused across multiple queries without side effects.
 */
class UnionBuilder
{
    /**
     * Builds UNION and UNION ALL clauses
     *
     * @param QueryState $state The query state containing union queries
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to use prepared statement mode
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @return string The UNION clauses
     */
    public function __invoke(
        QueryState $state,
        string $dialect,
        bool $prepared,
        array &$bindings
    ): string {
        $result = '';

        foreach ($state->getUnion() as $unionQuery) {
            $subResult = $unionQuery->sql($dialect, $prepared);

            if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                $result .= ' UNION ' . $subResult->sql();
                $bindings = array_merge($bindings, $subResult->bindings());
            } else {
                /** @phpstan-ignore binaryOp.invalid */
                $result .= ' UNION ' . $subResult;
            }
        }

        foreach ($state->getUnionAll() as $unionAllQuery) {
            $subResult = $unionAllQuery->sql($dialect, $prepared);

            if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                $result .= ' UNION ALL ' . $subResult->sql();
                $bindings = array_merge($bindings, $subResult->bindings());
            } else {
                /** @phpstan-ignore binaryOp.invalid */
                $result .= ' UNION ALL ' . $subResult;
            }
        }

        return $result;
    }
}
