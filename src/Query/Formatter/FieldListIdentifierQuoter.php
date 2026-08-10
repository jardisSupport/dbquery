<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Formatter;

/**
 * Stateless quoter for SELECT field lists
 *
 * Splits a comma separated field list at top level (respecting parentheses,
 * string literals and quoted identifiers) and quotes every item that is a
 * simple identifier (`ident` or `alias.ident`). For items of the form
 * `expr AS alias` the expr part is quoted if it is a simple identifier and
 * the alias is quoted if it is a simple identifier - keeping alias references
 * in ORDER BY / HAVING consistent with the declared alias. Everything else
 * ('*', functions, expressions, implicit aliases without AS, already quoted
 * strings) is returned byte-identical.
 *
 * Can be reused across multiple queries without side effects.
 */
class FieldListIdentifierQuoter
{
    /**
     * Quote simple identifiers in a SELECT field list
     *
     * @param string $fieldList The raw field list (e.g. 'id, name, COUNT(*) AS cnt')
     * @param callable $quoteSimpleIdentifier Callback quoting a simple identifier
     *        or returning the input unchanged: fn(string): string
     * @return string The field list with simple identifiers quoted
     */
    public function __invoke(string $fieldList, callable $quoteSimpleIdentifier): string
    {
        $trimmed = trim($fieldList);
        if ($trimmed === '' || $trimmed === '*') {
            return $fieldList;
        }

        $segments = $this->splitTopLevel($fieldList);

        $processed = array_map(
            fn(string $segment): string => $this->processSegment($segment, $quoteSimpleIdentifier),
            $segments
        );

        return implode(',', $processed);
    }

    /**
     * Process a single field list segment, preserving surrounding whitespace
     *
     * @param string $segment The raw segment including whitespace
     * @param callable $quoteSimpleIdentifier Callback quoting a simple identifier
     * @return string The processed segment
     */
    private function processSegment(string $segment, callable $quoteSimpleIdentifier): string
    {
        if (preg_match('/^(\s*)(.*?)(\s*)$/s', $segment, $matches) !== 1) {
            return $segment;
        }

        [, $lead, $core, $trail] = $matches;

        if ($core === '') {
            return $segment;
        }

        // Simple identifier or alias.ident - quote directly
        $quoted = $quoteSimpleIdentifier($core);
        if ($quoted !== $core) {
            return $lead . $quoted . $trail;
        }

        // expr AS alias - greedy first group binds to the LAST top-level AS
        if (preg_match('/^(.+)(\s+)([Aa][Ss])(\s+)([A-Za-z_][A-Za-z0-9_]*)$/s', $core, $asMatch) === 1) {
            [, $expr, $wsBefore, $asToken, $wsAfter, $alias] = $asMatch;

            return $lead
                . $quoteSimpleIdentifier($expr)
                . $wsBefore . $asToken . $wsAfter
                . $quoteSimpleIdentifier($alias)
                . $trail;
        }

        return $segment;
    }

    /**
     * Split a field list on top-level commas
     *
     * Commas inside parentheses, string literals ('...'), double-quoted
     * identifiers ("...") or backtick-quoted identifiers (`...`) do not split.
     *
     * @param string $fieldList The raw field list
     * @return array<int, string> The raw segments without the separating commas
     */
    private function splitTopLevel(string $fieldList): array
    {
        $segments = [];
        $current = '';
        $depth = 0;
        $inQuote = null;

        $length = strlen($fieldList);
        for ($i = 0; $i < $length; $i++) {
            $char = $fieldList[$i];

            if ($inQuote !== null) {
                $current .= $char;
                if ($char === $inQuote) {
                    $inQuote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inQuote = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $segments[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $segments[] = $current;

        return $segments;
    }
}
