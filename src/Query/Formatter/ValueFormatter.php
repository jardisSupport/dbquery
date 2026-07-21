<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Formatter;

use InvalidArgumentException;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless formatter for SQL values
 *
 * Formats values for SQL based on their type.
 * ONLY called in non-prepared mode for human-readable SQL generation.
 *
 * Supports:
 * - NULL values
 * - Boolean values (dialect-specific via callback)
 * - Numeric values (int, float)
 * - String values (validated and escaped via callbacks)
 * - Subqueries (DbQueryBuilderInterface)
 *
 * WARNING: Non-prepared mode should only be used for debugging/logging!
 * For production queries, always use prepared statements (prepared=true).
 */
class ValueFormatter
{
    /**
     * Formats a value for SQL based on its type
     *
     * @param mixed $value The value to format
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param callable $formatBoolean Callback to format boolean: fn(bool): string (dialect-specific)
     * @param callable $escapeString Callback to escape string: fn(string): string (dialect-specific)
     * @param callable $validateValue Callback to validate string: fn(string): void (throws on invalid)
     * @return string The formatted SQL value
     * @throws InvalidArgumentException If value type is unsupported or unsafe
     */
    public function __invoke(
        mixed $value,
        string $dialect,
        callable $formatBoolean,
        callable $escapeString,
        callable $validateValue
    ): string {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof DbQueryBuilderInterface) {
            /** @phpstan-ignore binaryOp.invalid */
            return '(' . $value->sql($dialect, false) . ')';
        }

        if (is_bool($value)) {
            return $formatBoolean($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $validateValue($value);  // Validate against SQL injection
            return "'" . $escapeString($value) . "'";  // Escape and quote
        }

        throw new InvalidArgumentException('Unsupported parameter type: ' . gettype($value));
    }
}
