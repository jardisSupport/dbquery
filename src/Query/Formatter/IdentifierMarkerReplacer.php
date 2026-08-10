<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Formatter;

/**
 * Stateless replacer for identifier markers in condition strings
 *
 * Condition fields are collected before the target dialect is known.
 * Simple identifier fields (see SimpleIdentifierQuoter::PATTERN) are wrapped
 * in a non-printable marker at collect time (ResolveField) and replaced with
 * the dialect-quoted identifier at build time by this replacer - the same
 * deferred-placeholder mechanism the package already uses for JSON and
 * subquery placeholders.
 *
 * The marker uses the control character \x01, which cannot occur in
 * legitimate SQL identifiers or expressions. Note the exact boundary: a
 * raw string that happens to contain a complete marker sequence
 * (\x01ID:ident\x01) WOULD be replaced by the quoted identifier - harmless
 * for SQL semantics but byte-altering. Legitimate SQL never contains \x01,
 * so this is a theoretical case, not an impossibility.
 *
 * Can be reused across multiple queries without side effects.
 */
class IdentifierMarkerReplacer
{
    /** Marker prefix wrapped around quotable identifiers at collect time */
    public const PREFIX = "\x01ID:";

    /** Marker suffix wrapped around quotable identifiers at collect time */
    public const SUFFIX = "\x01";

    /**
     * Replace all identifier markers with dialect-quoted identifiers
     *
     * @param string $sql The condition string possibly containing markers
     * @param callable $quoteSimpleIdentifier Callback quoting a simple identifier: fn(string): string
     * @return string The condition string with all markers replaced
     */
    public function __invoke(string $sql, callable $quoteSimpleIdentifier): string
    {
        if (strpos($sql, self::PREFIX) === false) {
            return $sql;
        }

        $result = preg_replace_callback(
            '/\x01ID:([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\x01/',
            static fn(array $matches): string => $quoteSimpleIdentifier($matches[1]),
            $sql
        );

        return is_string($result) ? $result : $sql;
    }
}
