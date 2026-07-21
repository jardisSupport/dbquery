<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

/**
 * Represents a window function with its specification
 *
 * Encapsulates a complete window function including:
 * - Function name (e.g., ROW_NUMBER, RANK, SUM)
 * - Function arguments (if any)
 * - Column alias for the result
 * - Window specification (PARTITION BY, ORDER BY, frame)
 *
 * Usage example:
 * ```php
 * $spec = new WindowSpec();
 * $spec->addPartition('department');
 * $spec->addOrder('salary', 'DESC');
 *
 * $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);
 *
 * // Generates: ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS row_num
 * ```
 */
class WindowFunction
{
    /**
     * @param string $function Window function name (e.g., 'ROW_NUMBER', 'RANK', 'SUM')
     * @param string $alias Column alias for the result
     * @param string|null $args Optional function arguments (e.g., 'amount' for SUM(amount))
     * @param WindowSpec $spec Window specification defining PARTITION BY, ORDER BY, frame
     */
    public function __construct(
        private readonly string $function,
        private readonly string $alias,
        private readonly ?string $args,
        private readonly WindowSpec $spec
    ) {
    }

    /**
     * Get the window function name
     *
     * @return string
     */
    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * Get the column alias
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Get the function arguments
     *
     * @return string|null
     */
    public function getArgs(): ?string
    {
        return $this->args;
    }

    /**
     * Get the window specification
     *
     * @return WindowSpec
     */
    public function getSpec(): WindowSpec
    {
        return $this->spec;
    }
}
