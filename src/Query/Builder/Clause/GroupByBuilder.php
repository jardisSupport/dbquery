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
     * @param QueryState $state The query state containing groupBy columns
     * @return string The GROUP BY clause
     */
    public function __invoke(QueryState $state): string
    {
        return !empty($state->getGroupBy())
            ? ' GROUP BY ' . implode(', ', $state->getGroupBy())
            : '';
    }
}
