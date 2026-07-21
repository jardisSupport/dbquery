<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Query\Condition\QueryJsonCondition;
use JardisSupport\Contract\DbQuery\DbQueryJsonConditionBuilderInterface;

/**
 * Stateless builder for JSON OR clause initialization
 *
 * Can be reused across DbQuery, DbUpdate, DbDelete without side effects.
 */
class OrJson
{
    /**
     * Initialize JSON OR condition
     *
     * @param QueryConditionCollector $collector The condition collector
     * @param QueryJsonCondition $queryJsonCondition The JSON condition builder
     * @param string $field The JSON field name
     * @param string|null $openBracket Opening brackets
     * @return DbQueryJsonConditionBuilderInterface
     */
    public function __invoke(
        QueryConditionCollector $collector,
        QueryJsonCondition $queryJsonCondition,
        string $field,
        ?string $openBracket
    ): DbQueryJsonConditionBuilderInterface {
        $condition = count($collector->whereConditions()) ? ' OR ' : ' WHERE ';
        $queryJsonCondition->initCondition($field, $condition . $openBracket);

        return $queryJsonCondition;
    }
}
