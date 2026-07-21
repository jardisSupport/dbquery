<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless builder for fullJoin() method logic
 *
 * Adds FULL OUTER JOIN data to state.
 * Only available in DbQuery (SELECT queries).
 */
class FullJoin
{
    /**
     * Add FULL OUTER JOIN to state
     *
     * @template T of DbQueryBuilderInterface
     * @param JoinStateInterface $state The state object (QueryState)
     * @param T $context The calling context
     * @param string|DbQueryBuilderInterface $container Table or subquery
     * @param string $constraint JOIN condition
     * @param string|null $alias Optional alias
     * @return T Returns context for chaining
     */
    public function __invoke(
        JoinStateInterface $state,
        DbQueryBuilderInterface $context,
        string|DbQueryBuilderInterface $container,
        string $constraint,
        ?string $alias
    ): DbQueryBuilderInterface {
        $join = [
            'join' => 'FULL OUTER JOIN',
            'container' => $container,
            'alias' => $alias,
            'constraint' => $constraint
        ];
        $state->addJoin($join);

        return $context;
    }
}
