<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Stateless builder for limit() method logic
 *
 * Adds LIMIT (and OFFSET for DbQuery) data to state.
 * Can be reused across DbQuery, DbUpdate, DbDelete without side effects.
 */
class Limit
{
    /**
     * Add LIMIT to state
     *
     * @template T of DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     * @param LimitStateInterface $state The state object (QueryState, UpdateState, DeleteState)
     * @param T $context The calling context
     * @param int|null $limit Maximum number of rows
     * @param int|null $offset Starting offset (only for DbQuery)
     * @return T Returns context for chaining
     */
    public function __invoke(
        LimitStateInterface $state,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $context,
        ?int $limit,
        ?int $offset
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        // DbQuery supports both limit and offset
        if ($context instanceof DbQueryBuilderInterface) {
            $state->setLimit($limit, $offset);
        } else {
            // DbUpdate and DbDelete only support limit
            $state->setLimit($limit);
        }

        return $context;
    }
}
