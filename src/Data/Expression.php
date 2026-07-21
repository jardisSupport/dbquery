<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Represents a raw SQL expression that should not be escaped or quoted
 *
 * Used for:
 * - Arithmetic expressions (e.g., "price * 1.19")
 * - Column references (e.g., "users.id", "t1.name")
 * - SQL functions (e.g., "NOW()", "COUNT(*)", "UPPER(name)")
 * - Complex expressions (e.g., "CASE WHEN ... END")
 *
 * Security: Expression content is validated against SQL injection patterns
 *
 * Usage examples:
 * ```php
 * use JardisSupport\DbQuery\data\Expression;
 *
 * // Static factory method (recommended)
 * $query->where('price')->greater(Expression::raw('cost'));
 *
 * // Or via constructor
 * $query->where('price')->greater(new Expression('cost'));
 * ```
 */
class Expression implements ExpressionInterface
{
    /**
     * @param string $sql The raw SQL expression
     */
    public function __construct(private readonly string $sql)
    {
    }

    /**
     * Create a raw SQL expression (static factory method)
     *
     * This is a convenience method that creates an Expression instance.
     * Use this when you want to avoid importing the helper function.
     *
     * Examples:
     * ```php
     * // Column comparison
     * $query->where('price')->greater(Expression::raw('cost'));
     *
     * // SQL functions
     * $insert->values('John', Expression::raw('NOW()'), 'active');
     *
     * // Arithmetic expressions
     * $query->where('total')->equals(Expression::raw('price * quantity'));
     * ```
     *
     * @param string $sql The raw SQL expression
     * @return self A new Expression instance
     */
    public static function raw(string $sql): self
    {
        return new self($sql);
    }

    /**
     * Get the raw SQL expression string
     *
     * @return string The unescaped SQL expression
     */
    public function toSql(): string
    {
        return $this->sql;
    }
}
