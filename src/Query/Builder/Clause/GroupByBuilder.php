<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\QueryState;

/**
 * Stateless builder for GROUP BY clause
 *
 * Builds the GROUP BY portion of a SQL query.
 * Can be reused across multiple queries without side effects.
 */
class GroupByBuilder
{
    /**
     * Builds GROUP BY clause
     *
     * Columns that are simple identifiers are quoted; expressions stay untouched.
     *
     * @param QueryState $state The query state containing groupBy columns
     * @param callable $quoteSimpleIdentifier Callback quoting a simple identifier
     *        or returning the input unchanged: fn(string): string
     * @return string The GROUP BY clause
     */
    public function __invoke(QueryState $state, callable $quoteSimpleIdentifier): string
    {
        if (empty($state->getGroupBy())) {
            return '';
        }

        $columns = array_map(
            static fn(string $column): string => $quoteSimpleIdentifier($column),
            $state->getGroupBy()
        );

        return ' GROUP BY ' . implode(', ', $columns);
    }
}
