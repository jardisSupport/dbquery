<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Formatter;

/**
 * Stateless quoter for simple SQL identifiers
 *
 * Quotes a candidate string dialect-specifically if - and only if - it is a
 * SIMPLE identifier: `ident` or `alias.ident` where ident matches
 * [A-Za-z_][A-Za-z0-9_]* (see PATTERN). Anything else (functions, operators,
 * '*', 'alias.*', already quoted strings, expressions, whitespace) is
 * returned byte-identical.
 *
 * Can be reused across multiple queries without side effects.
 */
class SimpleIdentifierQuoter
{
    /**
     * Boundary of the auto-quoting: a simple identifier is `ident` or
     * `alias.ident`, each part starting with a letter or underscore followed
     * by letters, digits or underscores. Nothing else is ever touched.
     */
    public const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    /**
     * SQL literals and niladic (argument-less) functions that match PATTERN
     * but must never be quoted (checked case-insensitively, single-part
     * candidates only - a qualified `alias.ident` is always an identifier).
     * NULL/TRUE/FALSE/DEFAULT and CURRENT_* per SQL standard (all three
     * dialects), LOCALTIME/LOCALTIMESTAMP (PostgreSQL/MySQL),
     * CURRENT_USER/SESSION_USER (PostgreSQL/MySQL niladic user literals).
     * A column literally named e.g. "null" must be passed pre-quoted or via
     * Expression::raw().
     */
    public const KEYWORD_EXCEPTIONS = [
        'NULL',
        'TRUE',
        'FALSE',
        'DEFAULT',
        'CURRENT_TIMESTAMP',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'LOCALTIME',
        'LOCALTIMESTAMP',
        'CURRENT_USER',
        'SESSION_USER',
    ];

    /**
     * Quote a simple identifier, leave anything else unchanged
     *
     * @param string $identifier The candidate string
     * @param callable $quoteIdentifier Dialect quote callback: fn(string): string
     * @return string The quoted identifier or the unchanged input
     */
    public function __invoke(string $identifier, callable $quoteIdentifier): string
    {
        if (preg_match(self::PATTERN, $identifier) !== 1) {
            return $identifier;
        }

        if (
            strpos($identifier, '.') === false
            && in_array(strtoupper($identifier), self::KEYWORD_EXCEPTIONS, true)
        ) {
            return $identifier;
        }

        $parts = array_map(
            static fn(string $part): string => $quoteIdentifier($part),
            explode('.', $identifier)
        );

        return implode('.', $parts);
    }
}
