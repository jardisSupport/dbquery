<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless builder for rightJoin() method logic
 *
 * Adds RIGHT JOIN data to state.
 * Only available in DbQuery (SELECT queries).
 */
class RightJoin
{
    /**
     * Add RIGHT JOIN to state
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
            'join' => 'RIGHT JOIN',
            'container' => $container,
            'alias' => $alias,
            'constraint' => $constraint
        ];
        $state->addJoin($join);

        return $context;
    }
}
