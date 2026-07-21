<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Holds the complete state of a SQL UPDATE command being built
 *
 * This class encapsulates all UPDATE-specific components:
 * - Table name and alias
 * - SET data (columns and values)
 * - JOINs (for MySQL/MariaDB)
 * - ORDER BY (for MySQL/MariaDB)
 * - LIMIT (for MySQL/MariaDB)
 *
 * Provides typed getter/setter methods for safe data access.
 */
class UpdateState implements JoinStateInterface, OrderByStateInterface, LimitStateInterface
{
    private string $table = '';
    private ?string $alias = null;

    /** @var array<string, string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface> */
    private array $setData = [];

    /** @var array<int, array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * }>
     */
    private array $joins = [];

    /** @var array<int, string> */
    private array $orderBy = [];

    private ?int $limit = null;

    private bool $ignore = false;

    // ==================== Getters ====================

    public function getTable(): string
    {
        return $this->table;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @return array<string, string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface>
     */
    public function getSetData(): array
    {
        return $this->setData;
    }

    /**
     * @return array<int, array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * }>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /** @return array<int, string> */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Get offset (not supported in UPDATE, always returns null)
     *
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return null;
    }

    public function isIgnore(): bool
    {
        return $this->ignore;
    }

    // ==================== Setters ====================

    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    public function setAlias(?string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * Set a single column value
     *
     * @param string $field
     * @param string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface $value
     */
    public function setColumn(
        string $field,
        string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface $value
    ): void {
        $this->setData[$field] = $value;
    }

    /**
     * Set multiple column values at once
     *
     * @param array<string, string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface> $data
     */
    public function setMultipleColumns(array $data): void
    {
        foreach ($data as $field => $value) {
            $this->setData[$field] = $value;
        }
    }

    /**
     * @param array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * } $join
     */
    public function addJoin(array $join): void
    {
        $this->joins[] = $join;
    }

    public function addOrderBy(string $column, string $direction = 'ASC'): void
    {
        $this->orderBy[] = $column . ' ' . $direction;
    }

    public function setLimit(?int $limit, ?int $offset = null): void
    {
        $this->limit = $limit;
        // UPDATE doesn't support OFFSET, so we ignore it
    }

    public function setIgnore(bool $ignore): void
    {
        $this->ignore = $ignore;
    }
}
