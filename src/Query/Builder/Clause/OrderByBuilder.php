<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;

/**
 * Stateless builder for ORDER BY clause
 *
 * Builds the ORDER BY portion of a SQL query.
 * Can be reused across multiple queries without side effects.
 */
class OrderByBuilder
{
    /**
     * Builds ORDER BY clause
     *
     * @param OrderByStateInterface $state The query state containing orderBy columns
     * @return string The ORDER BY clause
     */
    public function __invoke(OrderByStateInterface $state): string
    {
        return !empty($state->getOrderBy())
            ? ' ORDER BY ' . implode(', ', $state->getOrderBy())
            : '';
    }
}
