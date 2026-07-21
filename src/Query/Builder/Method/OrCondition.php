<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Query\Condition\QueryCondition;
use JardisSupport\Contract\DbQuery\DbQueryConditionBuilderInterface;

/**
 * Stateless builder for OR clause initialization
 *
 * Supports both WHERE and HAVING contexts (DbQuery specific).
 * Can be reused across DbQuery, DbUpdate, DbDelete without side effects.
 */
class OrCondition
{
    /**
     * Initialize OR condition
     *
     * @param QueryConditionCollector $collector The condition collector
     * @param QueryCondition $queryCondition The condition builder instance
     * @param string|null $field The field name (already resolved)
     * @param string|null $openBracket Opening brackets
     * @param bool $supportsHaving Whether context supports HAVING (DbQuery=true, others=false)
     * @return DbQueryConditionBuilderInterface
     */
    public function __invoke(
        QueryConditionCollector $collector,
        QueryCondition $queryCondition,
        ?string $field,
        ?string $openBracket,
        bool $supportsHaving = false
    ): DbQueryConditionBuilderInterface {
        if ($supportsHaving && count($collector->havingConditions()) > 0) {
            // HAVING context
            $queryCondition->initCondition(' OR ' . $openBracket, $field, true);
        } else {
            // WHERE context
            $condition = count($collector->whereConditions()) ? ' OR ' : ' WHERE ';
            $queryCondition->initCondition($condition . $openBracket, $field, false);
        }

        return $queryCondition;
    }
}
