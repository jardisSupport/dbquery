<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;

/**
 * Stateless builder for LIMIT and OFFSET clauses
 *
 * Builds the LIMIT/OFFSET portion of a SQL query.
 * Can be reused across multiple queries without side effects.
 */
class LimitBuilder
{
    /**
     * Builds LIMIT and OFFSET clause
     *
     * @param LimitStateInterface $state The query state containing limit and offset
     * @return string The LIMIT/OFFSET clause
     */
    public function __invoke(LimitStateInterface $state): string
    {
        if ($state->getLimit() === null) {
            return '';
        }

        $sql = ' LIMIT ' . $state->getLimit();

        if ($state->getOffset() !== null && $state->getOffset() > 0) {
            $sql .= ' OFFSET ' . $state->getOffset();
        }

        return $sql;
    }
}
