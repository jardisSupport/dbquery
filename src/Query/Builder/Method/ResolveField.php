<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Query\Formatter\IdentifierMarkerReplacer;
use JardisSupport\DbQuery\Query\Formatter\SimpleIdentifierQuoter;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Stateless helper to resolve field parameter
 *
 * Converts ExpressionInterface to its raw SQL string (never quoted, never
 * marked - Expression is the escape hatch for raw output). Plain strings
 * that are SIMPLE identifiers (`ident` or `alias.ident`, see
 * SimpleIdentifierQuoter::PATTERN) are wrapped in an identifier marker so
 * they get dialect-quoted at build time. All other strings and null pass
 * through unchanged.
 *
 * Can be reused across multiple queries without side effects.
 */
class ResolveField
{
    /**
     * Resolve field to string
     *
     * @param string|ExpressionInterface|null $field The field to resolve
     * @return string|null Resolved field (marked when a simple identifier) or null
     */
    public function __invoke(string|ExpressionInterface|null $field): ?string
    {
        if ($field instanceof ExpressionInterface) {
            return $field->toSql();
        }

        if ($field !== null && preg_match(SimpleIdentifierQuoter::PATTERN, $field) === 1) {
            return IdentifierMarkerReplacer::PREFIX . $field . IdentifierMarkerReplacer::SUFFIX;
        }

        return $field;
    }
}
