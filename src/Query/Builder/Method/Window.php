<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Query\Builder\Window\WindowBuilder;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbWindowBuilderInterface;

/**
 * Stateless builder for window() method logic
 *
 * Creates a named window builder and registers it in state.
 * Can be reused across multiple queries without side effects.
 */
class Window
{
    /**
     * Start building a named window
     *
     * @param QueryState $state The query state
     * @param DbQueryBuilderInterface $context The calling context
     * @param string $name The name of the window
     * @return DbWindowBuilderInterface Returns window builder for method chaining
     */
    public function __invoke(
        QueryState $state,
        DbQueryBuilderInterface $context,
        string $name
    ): DbWindowBuilderInterface {
        return new WindowBuilder($state, $context, '', '', null, true, $name);
    }
}
