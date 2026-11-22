<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use InvalidArgumentException;
use JardisSupport\Contract\DbQuery\ExpressionInterface;
use JardisSupport\DbQuery\Query\Formatter\IdentifierMarkerReplacer;
use JardisSupport\DbQuery\Query\Formatter\SimpleIdentifierQuoter;

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
 *
 * // Typed factories (no raw text): integer literal and dialect-quoted column
 * $query->where(Expression::integer(1))->equals($subQuery);
 * $query->where('o.user_id')->equals(Expression::column('u.id'));
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
     * Create an integer literal expression (static factory method)
     *
     * The integer is rendered as a decimal literal, never as a binding and
     * never quoted. Typed alternative to `Expression::raw('1')`, e.g. as the
     * left side of a single-value condition:
     * `where(Expression::integer(1))->equals($subQuery)`.
     *
     * @param int $value The integer literal
     * @return self A new Expression instance
     */
    public static function integer(int $value): self
    {
        return new self((string) $value);
    }

    /**
     * Create a column reference expression (static factory method)
     *
     * The column is quoted dialect-specifically at build time (MySQL/MariaDB/
     * SQLite: backtick, PostgreSQL: double quote), on the left side of a
     * condition as well as on the right side of its operator. Accepts only a
     * simple identifier (`ident` or `alias.ident`, see
     * SimpleIdentifierQuoter::PATTERN); anything else throws.
     *
     * Intended for WHERE/AND/OR/HAVING conditions: toSql() returns the column
     * wrapped in an identifier marker (see IdentifierMarkerReplacer), which the
     * SQL builders replace with the quoted identifier when the condition is
     * built. Use `Expression::raw()` for any other usage of an Expression.
     *
     * @param string $identifier Simple identifier, e.g. `id` or `u.id`
     * @return self A new Expression instance
     * @throws InvalidArgumentException If the value is not a simple identifier
     */
    public static function column(string $identifier): self
    {
        // Modifier D: a plain `$` would also accept a trailing newline
        if (preg_match(SimpleIdentifierQuoter::PATTERN . 'D', $identifier) !== 1) {
            throw new InvalidArgumentException(
                'Expression::column() accepts only a simple identifier (ident or alias.ident), got: '
                . $identifier
            );
        }

        return new self(IdentifierMarkerReplacer::PREFIX . $identifier . IdentifierMarkerReplacer::SUFFIX);
    }

    /**
     * Build a JOIN ON condition from column pairs (static factory method)
     *
     * Each pair `[left, right]` becomes `<left> = <right>`, pairs are joined with
     * ` AND `. Every side is a column reference as in {@see self::column()}, quoted
     * dialect-specifically at build time (MySQL/MariaDB/SQLite: backtick, PostgreSQL:
     * double quote) - so reserved words (`group`, `user`) and camelCase aliases work
     * on every dialect. Returns a `string` because the join methods take
     * `string $constraint`; the JOIN builder replaces the markers in the ON condition.
     *
     * ```php
     * $on = Expression::joinOn([['group.tenantId', 'user.tenantId'], ['group.id', 'user.groupId']]);
     * $query->innerJoin('users', $on, 'user');
     * ```
     *
     * @param array<int, mixed> $pairs List of `[left, right]` simple identifiers (`ident` or `alias.ident`)
     * @return string The ON condition with identifier markers
     * @throws InvalidArgumentException If the list is empty, a pair is not a list of two strings,
     *                                  or a side is not a simple identifier
     */
    public static function joinOn(array $pairs): string
    {
        if ($pairs === []) {
            throw new InvalidArgumentException('Expression::joinOn() needs at least one column pair');
        }

        $conditions = [];
        foreach ($pairs as $pair) {
            if (
                !is_array($pair)
                || count($pair) !== 2
                || !array_key_exists(0, $pair)
                || !array_key_exists(1, $pair)
                || !is_string($pair[0])
                || !is_string($pair[1])
            ) {
                throw new InvalidArgumentException(
                    'Expression::joinOn() expects each pair as [left, right] of two strings'
                );
            }

            $conditions[] = self::column($pair[0])->toSql() . ' = ' . self::column($pair[1])->toSql();
        }

        return implode(' AND ', $conditions);
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
