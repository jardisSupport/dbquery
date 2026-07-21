<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Validator;

use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * SQL Query Validator
 *
 * Validates SQL query structure before execution.
 * Currently validates bracket balance across all query parts.
 */
class QueryBracketValidator
{
    /**
     * Validates bracket balance in all query conditions
     *
     * @param array<int, string|array{
     *      type: string,
     *      container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *      closeBracket: ?string
     *  }> $whereConditions WHERE conditions from collector
     * @param array<int, string|array{
     *      type: string,
     *      container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *      closeBracket: ?string
     *  }> $havingConditions HAVING conditions from collector
     * @return bool True if all brackets are balanced
     */
    public function hasValidBrackets(
        array $whereConditions,
        array $havingConditions
    ): bool {
        $countOpenBrackets = 0;

        $countOpenBrackets = $this->validateConditionBrackets($whereConditions, $countOpenBrackets);
        $countOpenBrackets = $this->validateConditionBrackets($havingConditions, $countOpenBrackets);

        return $countOpenBrackets === 0;
    }

    /**
     * Validates bracket balance in a set of conditions
     *
     * @param array<int, string|array{
     *      type: string,
     *      container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *      closeBracket: ?string
     *  }> $conditions Array of condition strings
     * @param int $countOpenBrackets Current count of open brackets from previous validations
     * @return int Updated count of open brackets
     */
    private function validateConditionBrackets(array $conditions, int $countOpenBrackets): int
    {
        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $openBrackets = substr_count($condition, '(');
                $closeBrackets = substr_count($condition, ')');
                $countOpenBrackets += $openBrackets - $closeBrackets;
            } else {
                $closeBracket = $condition['closeBracket'] ?? '';
                $openBrackets = substr_count($closeBracket, '(');
                $closeBrackets = substr_count($closeBracket, ')');
                $countOpenBrackets += $openBrackets - $closeBrackets;
            }
        }

        return $countOpenBrackets;
    }
}
