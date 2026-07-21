<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless builder for JOIN clauses
 *
 * Builds all types of JOINs (INNER, LEFT, RIGHT, FULL OUTER, CROSS).
 * Can be reused across multiple queries without side effects.
 */
class JoinBuilder
{
    /**
     * Builds JOIN clauses for all join types
     *
     * @param JoinStateInterface $state The query state containing join definitions
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to use prepared statement mode
     * @param callable $quoteIdentifier Callback to quote identifiers: fn(string): string
     * @param callable $shouldSkipJoinType Callback to check if join type is supported: fn(string): bool
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @return string The JOIN clauses
     */
    public function __invoke(
        JoinStateInterface $state,
        string $dialect,
        bool $prepared,
        callable $quoteIdentifier,
        callable $shouldSkipJoinType,
        array &$bindings
    ): string {
        if (empty($state->getJoins())) {
            return '';
        }

        $result = [];
        foreach ($state->getJoins() as $joinData) {
            $joinType = $joinData['join'];

            if ($shouldSkipJoinType($joinType)) {
                throw new InvalidArgumentException(
                    sprintf('%s is not supported by this database dialect', $joinType)
                );
            }

            $container = $joinData['container'];
            $alias = $joinData['alias'];
            $constraint = $joinData['constraint'];

            if ($container instanceof DbQueryBuilderInterface) {
                $subResult = $container->sql($dialect, $prepared);

                if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                    $containerStr = '(' . $subResult->sql() . ')';
                    $bindings = array_merge($bindings, $subResult->bindings());
                } else {
                    /** @phpstan-ignore binaryOp.invalid */
                    $containerStr = '(' . $subResult . ')';
                }
            } else {
                $containerStr = $quoteIdentifier($container);
            }

            $aliasStr = $alias ? ' ' . $quoteIdentifier($alias) : '';
            $constraintStr = $constraint ? ' ON ' . $constraint : '';

            $result[] = $joinType . ' ' . $containerStr . $aliasStr . $constraintStr;
        }

        return ' ' . implode(' ', $result) . ' ';
    }
}
