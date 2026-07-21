<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

/**
 * Represents a window function that references a named window
 *
 * Used when a window function uses a previously defined named window
 * instead of an inline window specification.
 *
 * Usage example:
 * ```php
 * // First define a named window
 * $query->window('dept_window')
 *       ->partitionBy('department')
 *       ->windowOrderBy('salary', 'DESC')
 *       ->endWindow();
 *
 * // Then reference it
 * $ref = new WindowReference('ROW_NUMBER', 'dept_window', 'row_num', null);
 *
 * // Generates: ROW_NUMBER() OVER dept_window AS row_num
 * ```
 */
class WindowReference
{
    /**
     * @param string $function Window function name (e.g., 'ROW_NUMBER', 'RANK', 'SUM')
     * @param string $windowName Name of the referenced window
     * @param string $alias Column alias for the result
     * @param string|null $args Optional function arguments (e.g., 'amount' for SUM(amount))
     */
    public function __construct(
        private readonly string $function,
        private readonly string $windowName,
        private readonly string $alias,
        private readonly ?string $args
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
     * Get the referenced window name
     *
     * @return string
     */
    public function getWindowName(): string
    {
        return $this->windowName;
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
}
