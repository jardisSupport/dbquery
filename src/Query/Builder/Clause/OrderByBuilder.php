<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Clause;

use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;

/**
 * Stateless builder for ORDER BY clause
 *
 * Builds the ORDER BY portion of a SQL query.
 * Can be reused across multiple queries without side effects.
 */
class OrderByBuilder
{
    /**
     * Builds ORDER BY clause
     *
     * Each entry is stored as '<field> <ASC|DESC>'. The field part is quoted
     * when it is a simple identifier; expressions stay untouched.
     *
     * @param OrderByStateInterface $state The query state containing orderBy columns
     * @param callable $quoteSimpleIdentifier Callback quoting a simple identifier
     *        or returning the input unchanged: fn(string): string
     * @return string The ORDER BY clause
     */
    public function __invoke(OrderByStateInterface $state, callable $quoteSimpleIdentifier): string
    {
        if (empty($state->getOrderBy())) {
            return '';
        }

        $entries = array_map(
            static function (string $entry) use ($quoteSimpleIdentifier): string {
                if (preg_match('/^(.*\S)(\s+)(ASC|DESC)$/', $entry, $matches) === 1) {
                    return $quoteSimpleIdentifier($matches[1]) . $matches[2] . $matches[3];
                }

                return $entry;
            },
            $state->getOrderBy()
        );

        return ' ORDER BY ' . implode(', ', $entries);
    }
}
