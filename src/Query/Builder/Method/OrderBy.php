<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Stateless builder for orderBy() method logic
 *
 * Adds ORDER BY data to state.
 * Can be reused across DbQuery, DbUpdate, DbDelete without side effects.
 */
class OrderBy
{
    /**
     * Add ORDER BY to state
     *
     * @template T of DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     * @param OrderByStateInterface $state The state object (QueryState, UpdateState, DeleteState)
     * @param T $context The calling context
     * @param string $field The column name to sort by
     * @param string $direction The sort direction: 'ASC' or 'DESC'
     * @return T Returns context for chaining
     */
    public function __invoke(
        OrderByStateInterface $state,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $context,
        string $field,
        string $direction
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $state->addOrderBy($field, strtoupper($direction));

        return $context;
    }
}
