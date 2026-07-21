<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Stateless builder for WHERE and HAVING conditions
 *
 * Processes condition arrays including EXISTS/NOT EXISTS subqueries and JSON placeholders.
 * Can be reused across multiple queries without side effects.
 */
class ConditionBuilder
{
    /**
     * Builds SQL conditions from condition array
     *
     * @param array<int, string|array{
     *      type: string,
     *      container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *      closeBracket: ?string
     *  }> $conditions Array of conditions to process
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to use prepared statement mode
     * @param callable $processJsonPlaceholders Callback to process JSON placeholders: fn(string): string
     * @param callable $replaceSubqueryPlaceholders Callback to replace subquery placeholders: fn(string): string
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @return string The processed conditions string
     */
    public function __invoke(
        array $conditions,
        string $dialect,
        bool $prepared,
        callable $processJsonPlaceholders,
        callable $replaceSubqueryPlaceholders,
        array &$bindings
    ): string {
        $result = '';

        if (!empty($conditions)) {
            $processedConditions = [];

            foreach ($conditions as $condition) {
                if (is_string($condition)) {
                    // Process string conditions and replace subquery placeholders if in prepared mode
                    if ($prepared) {
                        $condition = $replaceSubqueryPlaceholders($condition);
                    }
                    $processedConditions[] = $processJsonPlaceholders($condition);
                } else {
                    $prefix = empty($processedConditions) ? ' WHERE ' : ' AND ';
                    $subResult = $condition['container']->sql($dialect, $prepared);

                    if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                        $processedConditions[] = $prefix . $condition['type'] . ' ('
                            . $subResult->sql() . ')'
                            . ($condition['closeBracket'] ?? '');
                        $bindings = array_merge($bindings, $subResult->bindings());
                    } else {
                        /** @phpstan-ignore binaryOp.invalid */
                        $processedConditions[] = $prefix . $condition['type'] . ' ('
                            . $subResult . ')'
                            . ($condition['closeBracket'] ?? '');
                    }
                }
            }
            $result = implode(' ', $processedConditions);
        }

        return $result;
    }
}
