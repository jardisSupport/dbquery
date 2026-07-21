<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless builder for crossJoin() method logic
 *
 * Adds CROSS JOIN data to state.
 * Only available in DbQuery (SELECT queries).
 */
class CrossJoin
{
    /**
     * Add CROSS JOIN to state
     *
     * @template T of DbQueryBuilderInterface
     * @param JoinStateInterface $state The state object (QueryState)
     * @param T $context The calling context
     * @param string|DbQueryBuilderInterface $container Table or subquery
     * @param string|null $alias Optional alias
     * @return T Returns context for chaining
     */
    public function __invoke(
        JoinStateInterface $state,
        DbQueryBuilderInterface $context,
        string|DbQueryBuilderInterface $container,
        ?string $alias
    ): DbQueryBuilderInterface {
        $join = [
            'join' => 'CROSS JOIN',
            'container' => $container,
            'alias' => $alias,
            'constraint' => null
        ];
        $state->addJoin($join);

        return $context;
    }
}
