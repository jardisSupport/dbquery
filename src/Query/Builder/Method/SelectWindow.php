<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Query\Builder\Window\WindowBuilder;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbWindowBuilderInterface;

/**
 * Stateless builder for selectWindow() method logic
 *
 * Creates a window function builder and registers it in state.
 * Can be reused across multiple queries without side effects.
 */
class SelectWindow
{
    /**
     * Start building a window function
     *
     * @param QueryState $state The query state
     * @param DbQueryBuilderInterface $context The calling context
     * @param string $function Window function name (e.g., 'ROW_NUMBER', 'RANK', 'SUM')
     * @param string $alias Column alias for the result
     * @param string|null $args Optional function arguments
     * @return DbWindowBuilderInterface Returns window builder for method chaining
     */
    public function __invoke(
        QueryState $state,
        DbQueryBuilderInterface $context,
        string $function,
        string $alias,
        ?string $args
    ): DbWindowBuilderInterface {
        return new WindowBuilder($state, $context, $function, $alias, $args);
    }
}
