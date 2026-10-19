<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

/**
 * A LIKE pattern built from a literal search term
 *
 * `%`, `_` and the escape character inside the term lose their wildcard meaning.
 * Passed to `like()` / `notLike()`, the condition renders `LIKE ? ESCAPE '!'` -
 * the same clause with the same effect on MySQL, MariaDB, PostgreSQL and SQLite
 * (the escape character `!` needs no string-literal escaping in any dialect).
 *
 * Usage:
 * ```php
 * use JardisSupport\DbQuery\Data\LikePattern;
 *
 * $query->where('name')->like(LikePattern::contains('50%'));     // name LIKE '%50!%%' ESCAPE '!'
 * $query->where('name')->like(LikePattern::startsWith('a_b'));   // a_b%
 * $query->where('name')->notLike(LikePattern::endsWith('x'));    // %x
 * ```
 */
final class LikePattern
{
    public const ESCAPE = '!';

    private function __construct(private readonly string $pattern)
    {
    }

    /** Matches values that contain the literal term */
    public static function contains(string $term): self
    {
        return new self('%' . self::escape($term) . '%');
    }

    /** Matches values that begin with the literal term */
    public static function startsWith(string $term): self
    {
        return new self(self::escape($term) . '%');
    }

    /** Matches values that end with the literal term */
    public static function endsWith(string $term): self
    {
        return new self('%' . self::escape($term));
    }

    /** Matches values that equal the literal term (case rules of the column apply) */
    public static function literal(string $term): self
    {
        return new self(self::escape($term));
    }

    /** The escaped pattern to bind */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /** The clause to append after the pattern value */
    public function escapeClause(): string
    {
        return " ESCAPE '" . self::ESCAPE . "'";
    }

    private static function escape(string $term): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE . self::ESCAPE, self::ESCAPE . '%', self::ESCAPE . '_'],
            $term
        );
    }
}
