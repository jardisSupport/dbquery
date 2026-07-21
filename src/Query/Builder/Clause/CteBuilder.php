<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;

/**
 * Stateless builder for CTE (Common Table Expressions) clauses
 *
 * Builds WITH and WITH RECURSIVE clauses for SQL queries.
 * Can be reused across multiple queries without side effects.
 */
class CteBuilder
{
    /**
     * Builds CTE clause with regular and recursive CTEs
     *
     * @param QueryState $state The query state containing CTE definitions
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to use prepared statement mode
     * @param callable $quoteIdentifier Callback to quote identifiers: fn(string): string
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @return string The CTE clause (WITH ...)
     */
    public function __invoke(
        QueryState $state,
        string $dialect,
        bool $prepared,
        callable $quoteIdentifier,
        array &$bindings
    ): string {
        if (empty($state->getCte()) && empty($state->getCteRecursive())) {
            return '';
        }

        $parts = [];
        $isRecursive = !empty($state->getCteRecursive());
        $cteBindings = [];

        // Regular CTEs first
        foreach ($state->getCte() as $name => $query) {
            $subResult = $query->sql($dialect, $prepared);

            if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                $parts[] = $quoteIdentifier($name) . ' AS (' . $subResult->sql() . ')';
                $cteBindings = array_merge($cteBindings, $subResult->bindings());
            } else {
                /** @phpstan-ignore binaryOp.invalid */
                $parts[] = $quoteIdentifier($name) . ' AS (' . $subResult . ')';
            }
        }

        // Recursive CTEs
        foreach ($state->getCteRecursive() as $name => $query) {
            $subResult = $query->sql($dialect, $prepared);

            if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                $parts[] = $quoteIdentifier($name) . ' AS (' . $subResult->sql() . ')';
                $cteBindings = array_merge($cteBindings, $subResult->bindings());
            } else {
                /** @phpstan-ignore binaryOp.invalid */
                $parts[] = $quoteIdentifier($name) . ' AS (' . $subResult . ')';
            }
        }

        // Insert CTE bindings BEFORE main query bindings
        if (!empty($cteBindings)) {
            $bindings = array_merge($cteBindings, $bindings);
        }

        $prefix = $isRecursive ? 'WITH RECURSIVE ' : 'WITH ';

        return $prefix . implode(', ', $parts) . ' ';
    }
}
