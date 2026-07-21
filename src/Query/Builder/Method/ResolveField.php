<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Stateless helper to resolve field parameter
 *
 * Converts ExpressionInterface to SQL string, leaves strings and null as-is.
 * Can be reused across multiple queries without side effects.
 */
class ResolveField
{
    /**
     * Resolve field to string
     *
     * @param string|ExpressionInterface|null $field The field to resolve
     * @return string|null Resolved field or null
     */
    public function __invoke(string|ExpressionInterface|null $field): ?string
    {
        if ($field instanceof ExpressionInterface) {
            return $field->toSql();
        }
        return $field;
    }
}
