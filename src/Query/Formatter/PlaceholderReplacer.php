<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Formatter;

use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use UnexpectedValueException;

/**
 * Stateless replacer for SQL placeholders
 *
 * Handles two types of placeholder replacement:
 * 1. Complete SQL placeholder replacement for non-prepared mode
 * 2. Subquery placeholder replacement for prepared mode
 *
 * Can be reused across multiple queries without side effects.
 */
class PlaceholderReplacer
{
    /**
     * Replaces all placeholders in SQL with their bound values (non-prepared mode)
     *
     * @param string $sql The SQL string containing ? placeholders
     * @param array<int|string, mixed> $bindings The binding values (positional or named)
     * @param callable $formatValue Callback to format values: fn(mixed): string
     * @return string The SQL string with all placeholders replaced
     * @throws UnexpectedValueException If a placeholder has no corresponding binding
     */
    public function replaceAll(
        string $sql,
        array $bindings,
        callable $formatValue
    ): string {
        $bindingIndex = 0;

        $result = preg_replace_callback('/\?/', function ($matches) use (&$bindingIndex, $bindings, $formatValue) {
            if (!array_key_exists($bindingIndex, $bindings)) {
                throw new UnexpectedValueException(
                    "Binding at position $bindingIndex not found (total bindings: " .
                    count($bindings) . ")"
                );
            }

            $value = $bindings[$bindingIndex];
            $bindingIndex++;

            return $formatValue($value);
        }, $sql);

        return $result ?? $sql;
    }

    /**
     * Replaces subquery placeholders with actual subquery SQL (prepared mode)
     *
     * In prepared mode, subqueries need to be inlined (not bound as parameters).
     * This method identifies subquery bindings, extracts their SQL and bindings,
     * and merges everything correctly.
     *
     * $bindings holds the bindings of a whole statement, in placeholder order. The condition
     * owns the bindings starting at $cursor, one per `?`; bindings before $cursor belong to
     * earlier parts of the statement and stay untouched, bindings after the condition's own
     * stay behind its (possibly expanded) bindings. On return $cursor points at the first
     * binding after this condition, ready for the next condition of the same statement.
     *
     * @param string $condition The condition string with ? placeholders
     * @param array<int|string, mixed> &$bindings Reference to bindings array (modified in place)
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param int &$cursor Index of the first binding owned by this condition; advanced past it
     * @return string The condition with subquery placeholders replaced
     */
    public function replaceSubqueries(
        string $condition,
        array &$bindings,
        string $dialect,
        int &$cursor = 0
    ): string {
        $bindingIndex = $cursor;
        $newBindings = [];

        $result = preg_replace_callback(
            '/\?/',
            function ($matches) use (&$bindingIndex, &$newBindings, &$bindings, $dialect) {
                if (!array_key_exists($bindingIndex, $bindings)) {
                    return '?';  // Keep placeholder if no binding
                }

                $value = $bindings[$bindingIndex];
                $bindingIndex++;

                // If it's a subquery, replace with subquery SQL and merge bindings
                if ($value instanceof DbQueryBuilderInterface) {
                    $subResult = $value->sql($dialect, true);

                    if ($subResult instanceof DbPreparedQueryInterface) {
                        // Merge subquery bindings into new bindings array
                        $newBindings = array_merge($newBindings, $subResult->bindings());
                        return '(' . $subResult->sql() . ')';
                    }
                }

                // Keep non-subquery bindings
                $newBindings[] = $value;
                return '?';
            },
            $condition
        );

        // Splice the processed bindings back in between the untouched ones
        $bindings = array_merge(
            array_slice($bindings, 0, $cursor),
            $newBindings,
            array_slice($bindings, $bindingIndex)
        );
        $cursor += count($newBindings);

        return $result ?? $condition;
    }
}
