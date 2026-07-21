<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Stateless builder for innerJoin() method logic
 *
 * Adds INNER JOIN data to state.
 * Can be reused across DbQuery, DbUpdate, DbDelete without side effects.
 */
class InnerJoin
{
    /**
     * Add INNER JOIN to state
     *
     * @template T of DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     * @param JoinStateInterface $state The state object (QueryState, UpdateState, DeleteState)
     * @param T $context The calling context
     * @param string|DbQueryBuilderInterface $container Table or subquery
     * @param string $constraint JOIN condition
     * @param string|null $alias Optional alias
     * @return T Returns context for chaining
     */
    public function __invoke(
        JoinStateInterface $state,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $context,
        string|DbQueryBuilderInterface $container,
        string $constraint,
        ?string $alias
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $join = [
            'join' => 'INNER JOIN',
            'container' => $container,
            'alias' => $alias,
            'constraint' => $constraint
        ];
        $state->addJoin($join);

        return $context;
    }
}
