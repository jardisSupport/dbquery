<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\QueryState;

/**
 * Stateless builder for WINDOW clause
 *
 * Builds the WINDOW clause for named window specifications.
 * Can be reused across multiple queries without side effects.
 */
class WindowClauseBuilder
{
    /**
     * Builds WINDOW clause with named window definitions
     *
     * @param QueryState $state The query state containing named windows
     * @return string The WINDOW clause (empty string if no named windows)
     */
    public function __invoke(QueryState $state): string
    {
        $namedWindows = $state->getNamedWindows();

        if (empty($namedWindows)) {
            return '';
        }

        $windowDefs = [];
        foreach ($namedWindows as $name => $spec) {
            $windowDefs[] = $name . ' AS (' . $spec->toSql() . ')';
        }

        return ' WINDOW ' . implode(', ', $windowDefs);
    }
}
