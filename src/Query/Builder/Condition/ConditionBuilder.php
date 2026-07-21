<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Condition;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Builds and appends query conditions to the collector
 */
class ConditionBuilder
{
    /**
     * @param QueryConditionCollector $collector
     * @param string $currentCondition The complete condition string
     * @param string|null $comparePart The comparison part to append
     * @param string|null $closeBracket Optional closing bracket
     * @param bool $isHavingCondition Whether this is a HAVING condition
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $queryBuilder
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     */
    public function __invoke(
        QueryConditionCollector $collector,
        string $currentCondition,
        ?string $comparePart,
        ?string $closeBracket,
        bool $isHavingCondition,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $queryBuilder
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $condition = $currentCondition . ($comparePart ?? '') . ($closeBracket ?? '');

        if ($isHavingCondition) {
            $collector->addHavingCondition($condition);
        } else {
            $collector->addWhereCondition($condition);
        }

        return $queryBuilder;
    }
}
