<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Stateless builder for EXISTS clause
 *
 * Can be reused across DbQuery, DbUpdate, DbDelete without side effects.
 */
class Exists
{
    /**
     * Add EXISTS condition
     *
     * @param QueryConditionCollector $collector The condition collector
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $context The calling context
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query The subquery
     * @param string|null $closeBracket Closing brackets
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface Returns context for chaining
     */
    public function __invoke(
        QueryConditionCollector $collector,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $context,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query,
        ?string $closeBracket
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $collector->addExistsCondition('EXISTS', $query, $closeBracket);

        return $context;
    }
}
