<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Holds the complete state of a SQL DELETE command being built
 *
 * This class encapsulates all DELETE-specific components:
 * - Table name and alias
 * - JOINs (for MySQL/MariaDB)
 * - ORDER BY (for MySQL/MariaDB)
 * - LIMIT (for MySQL/MariaDB)
 *
 * Provides typed getter/setter methods for safe data access.
 */
class DeleteState implements JoinStateInterface, OrderByStateInterface, LimitStateInterface
{
    private string $table = '';
    private ?string $alias = null;

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
     * Get offset (not supported in DELETE, always returns null)
     *
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return null;
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
        // DELETE doesn't support OFFSET, so we ignore it
    }
}
