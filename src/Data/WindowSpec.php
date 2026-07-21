<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

/**
 * Represents a window specification for SQL window functions
 *
 * Encapsulates PARTITION BY, ORDER BY, and frame specifications
 * that define how a window function operates on a set of rows.
 *
 * Window specifications are used in:
 * - Inline window functions: FUNCTION() OVER (window_spec)
 * - Named windows: WINDOW name AS (window_spec)
 *
 * Usage example:
 * ```php
 * $spec = new WindowSpec();
 * $spec->addPartition('department');
 * $spec->addOrder('salary', 'DESC');
 * $spec->setFrame('ROWS', '2 PRECEDING', 'CURRENT ROW');
 *
 * echo $spec->toSql();
 * // Output: PARTITION BY department ORDER BY salary DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
 * ```
 */
class WindowSpec
{
    /** @var array<int, string> */
    private array $partitionBy = [];

    /** @var array<int, string> */
    private array $orderBy = [];

    private ?string $frameType = null;
    private ?string $frameStart = null;
    private ?string $frameEnd = null;

    /**
     * Add a PARTITION BY field
     *
     * @param string $field Column name to partition by
     */
    public function addPartition(string $field): void
    {
        $this->partitionBy[] = $field;
    }

    /**
     * Add an ORDER BY field with direction
     *
     * @param string $field Column name to order by
     * @param string $direction Sort direction (ASC or DESC)
     */
    public function addOrder(string $field, string $direction): void
    {
        $this->orderBy[] = $field . ' ' . strtoupper($direction);
    }

    /**
     * Set frame specification
     *
     * @param string $type Frame type (ROWS, RANGE, GROUPS)
     * @param string $start Frame start boundary
     * @param string $end Frame end boundary
     */
    public function setFrame(string $type, string $start, string $end): void
    {
        $this->frameType = strtoupper($type);
        $this->frameStart = strtoupper($start);
        $this->frameEnd = strtoupper($end);
    }

    /**
     * Get PARTITION BY fields
     *
     * @return array<int, string>
     */
    public function getPartitionBy(): array
    {
        return $this->partitionBy;
    }

    /**
     * Get ORDER BY fields
     *
     * @return array<int, string>
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * Get frame type
     *
     * @return string|null
     */
    public function getFrameType(): ?string
    {
        return $this->frameType;
    }

    /**
     * Get frame start boundary
     *
     * @return string|null
     */
    public function getFrameStart(): ?string
    {
        return $this->frameStart;
    }

    /**
     * Get frame end boundary
     *
     * @return string|null
     */
    public function getFrameEnd(): ?string
    {
        return $this->frameEnd;
    }

    /**
     * Check if this spec has any content
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->partitionBy)
            && empty($this->orderBy)
            && $this->frameType === null;
    }

    /**
     * Build the window specification SQL
     *
     * @return string The SQL representation of the window specification
     */
    public function toSql(): string
    {
        $parts = [];

        if (!empty($this->partitionBy)) {
            $parts[] = 'PARTITION BY ' . implode(', ', $this->partitionBy);
        }

        if (!empty($this->orderBy)) {
            $parts[] = 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->frameType !== null && $this->frameStart !== null && $this->frameEnd !== null) {
            $parts[] = $this->frameType . ' BETWEEN ' . $this->frameStart . ' AND ' . $this->frameEnd;
        }

        return implode(' ', $parts);
    }
}
