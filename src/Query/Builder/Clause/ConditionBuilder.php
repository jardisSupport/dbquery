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
     * @param callable $quoteMarkedIdentifiers Callback to replace identifier markers with
     *        dialect-quoted identifiers: fn(string): string
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @param int|null &$cursor Index in $bindings of the first binding owned by these conditions. It is the
     *        same position the $replaceSubqueryPlaceholders callback works from and advances; the bindings
     *        of an EXISTS subquery are spliced in at this position, in the order they stand in the SQL.
     *        Without a cursor (null) they are appended to $bindings.
     * @return string The processed conditions string
     */
    public function __invoke(
        array $conditions,
        string $dialect,
        bool $prepared,
        callable $processJsonPlaceholders,
        callable $replaceSubqueryPlaceholders,
        callable $quoteMarkedIdentifiers,
        array &$bindings,
        ?int &$cursor = null
    ): string {
        $result = '';

        if (!empty($conditions)) {
            $processedConditions = [];

            foreach ($conditions as $condition) {
                if (is_string($condition)) {
                    // Replace identifier markers with dialect-quoted identifiers
                    $condition = $quoteMarkedIdentifiers($condition);

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
                        $this->spliceBindings($bindings, $subResult->bindings(), $cursor);
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

    /**
     * Inserts bindings at the cursor (and moves the cursor behind them), or appends without a cursor
     *
     * @param array<int|string, mixed> &$bindings Bindings of the whole statement
     * @param array<int|string, mixed> $insert Bindings to insert
     * @param int|null &$cursor Insert position; null appends
     */
    private function spliceBindings(array &$bindings, array $insert, ?int &$cursor): void
    {
        if ($cursor === null) {
            $bindings = array_merge($bindings, $insert);

            return;
        }

        array_splice($bindings, $cursor, 0, array_values($insert));
        $cursor += count($insert);
    }
}
