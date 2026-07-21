<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Data\WindowReference;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Stateless builder for selectWindowRef() method logic
 *
 * Adds a window function reference to a named window in state.
 * Can be reused across multiple queries without side effects.
 */
class SelectWindowRef
{
    /**
     * Add a window function that references a named window
     *
     * @param QueryState $state The query state
     * @param DbQueryBuilderInterface $context The calling context
     * @param string $function Window function name (e.g., 'ROW_NUMBER', 'RANK', 'SUM')
     * @param string $windowName The name of the window to reference
     * @param string $alias Column alias for the result
     * @param string|null $args Optional function arguments
     * @return DbQueryBuilderInterface Returns context for chaining
     */
    public function __invoke(
        QueryState $state,
        DbQueryBuilderInterface $context,
        string $function,
        string $windowName,
        string $alias,
        ?string $args
    ): DbQueryBuilderInterface {
        $windowReference = new WindowReference($function, $windowName, $alias, $args);
        $state->addWindowReference($windowReference);

        return $context;
    }
}
